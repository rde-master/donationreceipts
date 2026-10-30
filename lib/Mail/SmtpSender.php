<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Mail;

use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\SettingsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends mail through one of the app's own SMTP accounts
 * (SettingsService::ACCOUNT_*) - independent of the mail server configured
 * in Nextcloud. Uses the symfony/mailer that Nextcloud ships.
 */
class SmtpSender {
    public function __construct(
        private SettingsService $settings,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * A plain-text mail with the account's sender address.
     *
     * @throws ValidationException account not configured
     */
    public function newEmail(string $account, string $to, string $subject, string $body): Email {
        $missing = $this->settings->missingForMail($account);
        if ($missing !== []) {
            throw new ValidationException('Bitte zuerst die E-Mail-Einstellungen vervollständigen: ' . implode(', ', $missing));
        }
        return (new Email())
            ->from(new Address($this->settings->getString($account . 'smtp_from_email'), $this->settings->getString($account . 'smtp_from_name')))
            ->to($to)
            ->subject(trim($subject))
            ->text($body);
    }

    /**
     * @throws ValidationException sending failed (message for the user)
     */
    public function send(string $account, Email $email): void {
        $error = $this->sendEach($account, [$email])[0];
        if ($error !== null) {
            throw new ValidationException('Versand fehlgeschlagen: ' . $error);
        }
    }

    /**
     * Sends several mails over one connection. A failed mail does not stop
     * the others; the connection is opened again for the next one.
     *
     * @template K of array-key
     * @param array<K, Email> $emails
     * @return array<K, string|null> error message per mail, null = sent
     */
    public function sendEach(string $account, array $emails): array {
        $transport = new StrictEsmtpTransport(
            $this->settings->getString($account . 'smtp_host'),
            (int)$this->settings->get($account . 'smtp_port'),
            $this->settings->get($account . 'smtp_security') === SettingsService::SMTP_SSL,
        );
        $user = $this->settings->getString($account . 'smtp_user');
        if ($user !== '') {
            $transport->setUsername($user);
            $transport->setPassword($this->settings->getSmtpPassword($account));
        }
        $errors = [];
        try {
            foreach ($emails as $key => $email) {
                try {
                    $transport->send($email);
                    $errors[$key] = null;
                } catch (TransportExceptionInterface $e) {
                    $this->logger->warning('Sending mail failed', ['exception' => $e]);
                    $errors[$key] = $e->getMessage();
                    $this->stop($transport);
                }
            }
        } finally {
            $this->stop($transport);
        }
        return $errors;
    }

    private function stop(StrictEsmtpTransport $transport): void {
        try {
            $transport->stop();
        } catch (\Throwable) {
        }
    }
}
