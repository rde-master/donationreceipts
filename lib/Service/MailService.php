<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Db\ReceiptMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Mail\StrictEsmtpTransport;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Mail\IMailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends the signed receipt through the app's own SMTP account - independent
 * of the mail server configured in Nextcloud. Uses the symfony/mailer that
 * Nextcloud ships.
 */
class MailService {
    public function __construct(
        private SettingsService $settings,
        private StorageService $storage,
        private DonorMapper $donors,
        private ReceiptMapper $receipts,
        private IMailer $mailer,
        private ITimeFactory $time,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * What would be sent: recipient, subject and text from the template.
     *
     * @return array{to: string, subject: string, body: string, attachment: string|null, missing: list<string>}
     */
    public function preview(Receipt $receipt): array {
        $donor = $this->donors->find($receipt->getDonorId());
        $values = MailTemplate::values($receipt, $donor, $this->settings->getString('org_name'));
        $file = $this->storage->getFile($receipt->getFileSignedId());
        return [
            'to' => (string)$donor->getEmail(),
            'subject' => MailTemplate::render($this->settings->getString('mail_subject'), $values),
            'body' => MailTemplate::render($this->settings->getString('mail_body'), $values),
            'attachment' => $file?->getName(),
            'missing' => $this->settings->missingForMail(),
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

        $email = $this->newEmail($to, $subject, $body)
            ->attach($file->getContent(), $file->getName(), 'application/pdf');
        $this->deliver($email);

        $receipt->setSentAt($this->time->getTime());
        $receipt->setSentTo($to);
        $receipt->setChangedAfterSent(false);
        return $this->receipts->update($receipt);
    }

    /**
     * @throws ValidationException
     */
    public function sendTest(string $to): void {
        if (!$this->mailer->validateMailAddress(trim($to))) {
            throw new ValidationException('Die Empfängeradresse ist ungültig');
        }
        $this->deliver($this->newEmail(trim($to), 'Testmail Spendenbescheinigungen',
            "Diese Testmail bestätigt, dass der SMTP-Zugang der App Spendenbescheinigungen funktioniert."));
    }

    /**
     * @throws ValidationException
     */
    private function newEmail(string $to, string $subject, string $body): Email {
        $missing = $this->settings->missingForMail();
        if ($missing !== []) {
            throw new ValidationException('Bitte zuerst die E-Mail-Einstellungen vervollständigen: ' . implode(', ', $missing));
        }
        return (new Email())
            ->from(new Address($this->settings->getString('smtp_from_email'), $this->settings->getString('smtp_from_name')))
            ->to($to)
            ->subject(trim($subject))
            ->text($body);
    }

    /**
     * @throws ValidationException
     */
    private function deliver(Email $email): void {
        $transport = new StrictEsmtpTransport(
            $this->settings->getString('smtp_host'),
            (int)$this->settings->get('smtp_port'),
            $this->settings->get('smtp_security') === SettingsService::SMTP_SSL,
        );
        $user = $this->settings->getString('smtp_user');
        if ($user !== '') {
            $transport->setUsername($user);
            $transport->setPassword($this->settings->getSmtpPassword());
        }
        try {
            $transport->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->warning('Sending donation receipt mail failed', ['exception' => $e]);
            throw new ValidationException('Versand fehlgeschlagen: ' . $e->getMessage());
        } finally {
            try {
                $transport->stop();
            } catch (\Throwable) {
            }
        }
    }
}
