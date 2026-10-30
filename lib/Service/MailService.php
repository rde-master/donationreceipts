<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Db\ReceiptMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Mail\SmtpSender;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Mail\IMailer;

/**
 * Sends the signed receipt through the app's own SMTP account for receipts.
 */
class MailService {
    public function __construct(
        private SettingsService $settings,
        private StorageService $storage,
        private DonorMapper $donors,
        private ReceiptMapper $receipts,
        private IMailer $mailer,
        private ITimeFactory $time,
        private SmtpSender $sender,
        private FolderShareService $folderShares,
    ) {
    }

    /**
     * What would be sent: recipient, subject and text from the template.
     *
     * @return array{to: string, subject: string, body: string, attachment: string|null, missing: list<string>, error: string|null}
     */
    public function preview(Receipt $receipt): array {
        $donor = $this->donors->find($receipt->getDonorId());
        $file = $this->storage->getFile($receipt->getFileSignedId());
        $error = null;
        try {
            $mail = $this->render($receipt, $donor);
        } catch (ValidationException $e) {
            // e.g. the folder link could not be created; the text shows the placeholder
            $error = $e->getMessage();
            $mail = $this->render($receipt, $donor, false);
        }
        return [
            'to' => (string)$donor->getEmail(),
        ] + $mail + [
            'attachment' => $file?->getName(),
            'missing' => $this->settings->missingForMail(),
            'error' => $error,
        ];
    }

    /**
     * Sends the signed PDF and records time and recipient at the receipt.
     *
     * @throws ValidationException invalid input or sending failed (message for the user)
     */
    public function sendReceipt(Receipt $receipt, string $to, string $subject, string $body): Receipt {
        $to = trim($to);
        if (!$this->mailer->validateMailAddress($to)) {
            throw new ValidationException('Die Empfängeradresse ist ungültig');
        }
        if (trim($subject) === '') {
            throw new ValidationException('Der Betreff fehlt');
        }
        $file = $this->storage->getFile($receipt->getFileSignedId());
        if ($file === null) {
            throw new ValidationException('Die unterschriebene Fassung fehlt im Spenderordner. Speichere die Bescheinigung erneut.');
        }

        $email = $this->sender->newEmail(SettingsService::ACCOUNT_RECEIPTS, $to, $subject, $body)
            ->attach($file->getContent(), $file->getName(), 'application/pdf');
        $this->sender->send(SettingsService::ACCOUNT_RECEIPTS, $email);

        $receipt->setSentAt($this->time->getTime());
        $receipt->setSentTo($to);
        $receipt->setChangedAfterSent(false);
        return $this->receipts->update($receipt);
    }

    /**
     * Subject and text of the receipt mail from the settings' template.
     * {ordnerlink} is only resolved if the template uses it.
     *
     * @return array{subject: string, body: string}
     * @throws ValidationException the folder link could not be created
     */
    public function render(Receipt $receipt, Donor $donor, bool $withFolderLink = true): array {
        $subject = $this->settings->getString('mail_subject');
        $body = $this->settings->getString('mail_body');
        $values = MailTemplate::values($receipt, $donor, $this->settings->getString('org_name'));
        $placeholder = '{' . MailTemplate::FOLDER_LINK . '}';
        if ($withFolderLink && (str_contains($subject, $placeholder) || str_contains($body, $placeholder))) {
            $values[MailTemplate::FOLDER_LINK] = $this->folderShares->link($donor);
        }
        return [
            'subject' => MailTemplate::render($subject, $values),
            'body' => MailTemplate::render($body, $values),
        ];
    }

    /**
     * @throws ValidationException
     */
    public function sendTest(string $to, string $account = SettingsService::ACCOUNT_RECEIPTS): void {
        if (!$this->mailer->validateMailAddress(trim($to))) {
            throw new ValidationException('Die Empfängeradresse ist ungültig');
        }
        $label = $account === SettingsService::ACCOUNT_SPONSORS ? 'für Sponsoren-Anschreiben' : 'für Bescheinigungen';
        $this->sender->send($account, $this->sender->newEmail($account, trim($to), 'Testmail Spendenbescheinigungen',
            "Diese Testmail bestätigt, dass der SMTP-Zugang {$label} der App Spendenbescheinigungen funktioniert."));
    }
}
