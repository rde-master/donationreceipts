<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Mail;

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

/**
 * SMTP transport that never sends credentials or mail over an unencrypted
 * connection. Symfony 6.4 (shipped with Nextcloud) only uses STARTTLS when
 * the server offers it; a stripped STARTTLS would otherwise silently fall
 * back to plain text.
 *
 * EsmtpTransport runs the TLS handshake right after a successful STARTTLS
 * command and throws if it fails, so an accepted STARTTLS means the
 * connection is encrypted from then on.
 */
class StrictEsmtpTransport extends EsmtpTransport {
    private bool $upgraded = false;

    /**
     * @param bool $implicitTls true: TLS from the start (port 465); false: STARTTLS required
     */
    public function __construct(string $host, int $port, bool $implicitTls, float $timeout = 20.0) {
        parent::__construct($host, $port, $implicitTls);
        /** @var SocketStream $stream */
        $stream = $this->getStream();
        $stream->setTimeout($timeout);
    }

    public function executeCommand(string $command, array $codes): string {
        if (str_starts_with($command, 'AUTH ') || str_starts_with($command, 'MAIL FROM:')) {
            /** @var SocketStream $stream */
            $stream = $this->getStream();
            if (!$stream->isTLS() && !$this->upgraded) {
                throw new TransportException('Der Mailserver bietet keine verschlüsselte Verbindung (STARTTLS) an. Versand abgebrochen.');
            }
        }
        $response = parent::executeCommand($command, $codes);
        if ($command === "STARTTLS\r\n") {
            $this->upgraded = true;
        }
        return $response;
    }

    public function stop(): void {
        parent::stop();
        $this->upgraded = false;
    }
}
