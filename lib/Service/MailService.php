<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Db\ReceiptMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Mail\MailHtml;
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
     * The mail dialog: recipient, the template (subject and HTML text with
     * placeholders, editable before sending) and how it looks rendered.
     *
     * @return array{to: string, subject: string, body: string, preview: array{subject: string, html: string}, attachment: string|null, missing: list<string>, error: string|null, placeholders: array<string, string>}
     */
    public function preview(Receipt $receipt): array {
        $donor = $this->donors->find($receipt->getDonorId());
        $file = $this->storage->getFile($receipt->getFileSignedId());
        $template = $this->templates();
        $rendered = $this->renderPreview($receipt, $template['subject'], $template['body']);
        return [
            'to' => (string)$donor->getEmail(),
            'subject' => $template['subject'],
            'body' => $template['body'],
            'preview' => ['subject' => $rendered['subject'], 'html' => $rendered['html']],
            'attachment' => $file?->getName(),
            'missing' => $this->settings->missingForMail(),
            'error' => $rendered['error'],
            'placeholders' => MailTemplate::PLACEHOLDERS,
        ];
    }

    /**
     * A (possibly edited) template rendered for the receipt, as complete
     * HTML document for the preview.
     *
     * @return array{subject: string, html: string, error: string|null}
     */
    public function renderPreview(Receipt $receipt, string $subject, string $body): array {
        $donor = $this->donors->find($receipt->getDonorId());
        $error = null;
        try {
            $mail = $this->render($receipt, $donor, $subject, $body);
        } catch (ValidationException $e) {
            // e.g. the folder link could not be created; the text shows the placeholder
            $error = $e->getMessage();
            $mail = $this->render($receipt, $donor, $subject, $body, false);
        }
        return ['subject' => $mail['subject'], 'html' => MailHtml::document($mail['html']), 'error' => $error];
    }

    /**
     * Subject and HTML text of the receipt mail as set in the settings.
     *
     * @return array{subject: string, body: string}
     */
    public function templates(): array {
        return [
            'subject' => $this->settings->getString('mail_subject'),
            'body' => $this->settings->getString('mail_body'),
        ];
    }

    /**
     * Sends the signed PDF and records time and recipient at the receipt.
     * Subject and text are templates; they are filled in here.
     *
     * @throws ValidationException invalid input, folder link or sending failed (message for the user)
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

        $mail = $this->render($receipt, $this->donors->find($receipt->getDonorId()), $subject, $body);
        $email = $this->sender->newEmail(SettingsService::ACCOUNT_RECEIPTS, $to, $mail['subject'], $mail['html'])
            ->attach($file->getContent(), $file->getName(), 'application/pdf');
        $this->sender->send(SettingsService::ACCOUNT_RECEIPTS, $email);

        $receipt->setSentAt($this->time->getTime());
        $receipt->setSentTo($to);
        $receipt->setChangedAfterSent(false);
        return $this->receipts->update($receipt);
    }

    /**
     * Fills a template for the receipt. The HTML is sanitized first;
     * {ordnerlink}/{ordnerknopf} are only resolved if used.
     *
     * @return array{subject: string, html: string} html: body fragment
     * @throws ValidationException the folder link could not be created
     */
    public function render(Receipt $receipt, Donor $donor, string $subject, string $body, bool $withFolderLink = true): array {
        $body = MailHtml::sanitize($body);
        $values = MailTemplate::values($receipt, $donor, $this->settings->getString('org_name'));
        if ($withFolderLink && preg_match('/\{(' . MailTemplate::FOLDER_LINK . '|ordnerknopf)\}/', $subject . $body) === 1) {
            $values[MailTemplate::FOLDER_LINK] = $this->folderShares->link($donor);
        }
        return [
            'subject' => MailTemplate::render($subject, $values),
            'html' => MailHtml::render($body, $values),
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
            "<p>Diese Testmail bestätigt, dass der SMTP-Zugang {$label} der App Spendenbescheinigungen funktioniert.</p>"));
    }
}
