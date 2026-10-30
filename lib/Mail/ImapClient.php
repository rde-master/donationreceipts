<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Mail;

/**
 * Minimal read-only IMAP client for fetching replies to sponsor letters:
 * login, EXAMINE (read-only, so the \Seen flags stay as they are), UID
 * SEARCH and UID FETCH of whole messages. Like StrictEsmtpTransport it
 * refuses unencrypted connections: TLS from the start (993) or STARTTLS
 * (143), with certificate verification.
 *
 * Nextcloud ships no IMAP library and ext-imap left PHP core with 8.4,
 * hence this small implementation of the few commands needed (RFC 9051).
 */
class ImapClient {
    private int $tag = 0;

    /**
     * @param resource $stream
     */
    public function __construct(
        private $stream,
    ) {
    }

    /**
     * @param string|null $caFile CA bundle for certificate verification (null = system default)
     * @throws ImapException
     */
    public static function connect(string $host, int $port, bool $implicitTls, ?string $caFile = null, float $timeout = 20.0): self {
        $ssl = ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host];
        if ($caFile !== null && is_file($caFile)) {
            $ssl['cafile'] = $caFile;
        }
        $context = stream_context_create(['ssl' => $ssl]);
        $address = ($implicitTls ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $stream = @stream_socket_client($address, $errno, $error, $timeout, STREAM_CLIENT_CONNECT, $context);
        if ($stream === false) {
            throw new ImapException("Keine Verbindung zu {$host}:{$port}: {$error}");
        }
        stream_set_timeout($stream, (int)ceil($timeout));
        $client = new self($stream);
        $greeting = $client->readLine();
        if (!str_starts_with($greeting, '* OK') && !str_starts_with($greeting, '* PREAUTH')) {
            throw new ImapException('Unerwartete Begrüßung des IMAP-Servers: ' . trim($greeting));
        }
        if (!$implicitTls) {
            $client->command('STARTTLS');
            if (@stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                throw new ImapException('Die verschlüsselte Verbindung (STARTTLS) zum IMAP-Server ist fehlgeschlagen');
            }
        }
        return $client;
    }

    /**
     * @throws ImapException
     */
    public function login(string $user, string $password): void {
        try {
            $this->command('LOGIN', [$user, $password]);
        } catch (ImapException) {
            throw new ImapException('Anmeldung am IMAP-Server fehlgeschlagen – Benutzer oder Passwort falsch?');
        }
    }

    /**
     * Opens a folder read-only.
     *
     * @return array{uidValidity: int, uidNext: int, exists: int}
     * @throws ImapException
     */
    public function examine(string $folder): array {
        $result = ['uidValidity' => 0, 'uidNext' => 0, 'exists' => 0];
        foreach ($this->command('EXAMINE', [mb_convert_encoding($folder, 'UTF7-IMAP', 'UTF-8')]) as $response) {
            $line = $response['line'];
            if (preg_match('/\[UIDVALIDITY (\d+)\]/i', $line, $m) === 1) {
                $result['uidValidity'] = (int)$m[1];
            } elseif (preg_match('/\[UIDNEXT (\d+)\]/i', $line, $m) === 1) {
                $result['uidNext'] = (int)$m[1];
            } elseif (preg_match('/^\* (\d+) EXISTS/i', $line, $m) === 1) {
                $result['exists'] = (int)$m[1];
            }
        }
        return $result;
    }

    /**
     * UIDs greater than $afterUid, ascending.
     *
     * @return list<int>
     * @throws ImapException
     */
    public function uidsAfter(int $afterUid): array {
        $uids = [];
        foreach ($this->command('UID SEARCH UID ' . ($afterUid + 1) . ':*') as $response) {
            if (preg_match('/^\* SEARCH\b(.*)$/i', $response['line'], $m) === 1) {
                foreach (preg_split('/\s+/', trim($m[1])) ?: [] as $uid) {
                    // "n:*" always matches the last message, even if its UID is lower
                    if ($uid !== '' && (int)$uid > $afterUid) {
                        $uids[] = (int)$uid;
                    }
                }
            }
        }
        sort($uids);
        return array_values(array_unique($uids));
    }

    /**
     * The raw message (at most $maxBytes; larger ones are cut off).
     *
     * @throws ImapException
     */
    public function fetch(int $uid, int $maxBytes): ?string {
        foreach ($this->command("UID FETCH {$uid} (BODY.PEEK[]<0.{$maxBytes}>)") as $response) {
            if (preg_match('/^\* \d+ FETCH/i', $response['line']) === 1 && $response['literals'] !== []) {
                return $response['literals'][0];
            }
        }
        return null;
    }

    public function logout(): void {
        try {
            $this->command('LOGOUT');
        } catch (\Throwable) {
        }
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /**
     * Sends a tagged command; arguments are sent as quoted strings or, if
     * they contain other than printable ASCII, as literals.
     *
     * @param list<string> $arguments
     * @return list<array{line: string, literals: list<string>}> untagged responses
     * @throws ImapException NO/BAD or connection lost
     */
    public function command(string $command, array $arguments = []): array {
        $tag = 'A' . (++$this->tag);
        $line = $tag . ' ' . $command;
        foreach ($arguments as $argument) {
            if (preg_match('/^[\x20-\x7e]*$/', $argument) === 1) {
                $line .= ' "' . addcslashes($argument, '"\\') . '"';
                continue;
            }
            // literal: announce the length, wait for the continuation
            $this->write($line . ' {' . strlen($argument) . "}\r\n");
            $continuation = $this->readLine();
            if (!str_starts_with($continuation, '+')) {
                throw new ImapException('Der IMAP-Server lehnt den Befehl ab: ' . trim($continuation));
            }
            $line = $argument;
        }
        $this->write($line . "\r\n");

        $responses = [];
        while (true) {
            $response = $this->readResponse();
            if (str_starts_with($response['line'], $tag . ' ')) {
                $status = substr($response['line'], strlen($tag) + 1);
                if (!str_starts_with(strtoupper($status), 'OK')) {
                    throw new ImapException('IMAP-Fehler: ' . trim($status));
                }
                return $responses;
            }
            $responses[] = $response;
        }
    }

    /**
     * One response, with its literals ({n} followed by n bytes) taken out.
     *
     * @return array{line: string, literals: list<string>}
     * @throws ImapException
     */
    private function readResponse(): array {
        $line = '';
        $literals = [];
        while (true) {
            $part = $this->readLine();
            if (preg_match('/\{(\d+)\}\r\n$/', $part, $m) === 1) {
                $line .= substr($part, 0, -2);
                $literals[] = $this->read((int)$m[1]);
                continue;
            }
            return ['line' => $line . rtrim($part, "\r\n"), 'literals' => $literals];
        }
    }

    /**
     * @throws ImapException
     */
    private function readLine(): string {
        $line = fgets($this->stream);
        if ($line === false) {
            throw new ImapException('Die Verbindung zum IMAP-Server wurde unterbrochen');
        }
        return $line;
    }

    /**
     * @throws ImapException
     */
    private function read(int $length): string {
        $data = '';
        while (strlen($data) < $length) {
            $chunk = fread($this->stream, $length - strlen($data));
            if ($chunk === false || ($chunk === '' && feof($this->stream))) {
                throw new ImapException('Die Verbindung zum IMAP-Server wurde unterbrochen');
            }
            $data .= $chunk;
        }
        return $data;
    }

    /**
     * @throws ImapException
     */
    private function write(string $data): void {
        if (@fwrite($this->stream, $data) !== strlen($data)) {
            throw new ImapException('Die Verbindung zum IMAP-Server wurde unterbrochen');
        }
    }
}
