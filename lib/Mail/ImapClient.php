<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Mail;

/**
 * Minimal IMAP client for replies to sponsor letters: login, EXAMINE
 * (read-only, so the \Seen flags stay as they are), UID SEARCH and UID FETCH
 * of whole messages; and for done replies SELECT, marking as read and
 * moving to a folder (MOVE, or COPY + delete). Like StrictEsmtpTransport it
 * refuses unencrypted connections: TLS from the start (993) or STARTTLS
 * (143), with certificate verification.
 *
 * Nextcloud ships no IMAP library and ext-imap left PHP core with 8.4,
 * hence this small implementation of the few commands needed (RFC 9051).
 */
class ImapClient {
    private int $tag = 0;
    /** @var list<string>|null upper case, from CAPABILITY */
    private ?array $capabilities = null;

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
        foreach ($this->command('EXAMINE', [self::folderName($folder)]) as $response) {
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
     * UIDs of messages received on or after the day of $timestamp, ascending.
     *
     * @return list<int>
     * @throws ImapException
     */
    public function uidsSince(int $timestamp): array {
        $uids = [];
        // IMAP dates use English month names, independent of the locale
        foreach ($this->command('UID SEARCH SINCE ' . gmdate('j-M-Y', $timestamp)) as $response) {
            if (preg_match('/^\* SEARCH\b(.*)$/i', $response['line'], $m) === 1) {
                foreach (preg_split('/\s+/', trim($m[1])) ?: [] as $uid) {
                    if ($uid !== '') {
                        $uids[] = (int)$uid;
                    }
                }
            }
        }
        sort($uids);
        return array_values(array_unique($uids));
    }

    /**
     * Opens a folder for changes (marking read, moving).
     *
     * @throws ImapException
     */
    public function select(string $folder): void {
        $this->command('SELECT', [self::folderName($folder)]);
    }

    /**
     * UIDs of the messages with this Message-ID in the selected folder.
     *
     * @return list<int>
     * @throws ImapException
     */
    public function uidsByMessageId(string $messageId): array {
        $uids = [];
        foreach ($this->command('UID SEARCH HEADER Message-ID', [$messageId]) as $response) {
            if (preg_match('/^\* SEARCH\b(.*)$/i', $response['line'], $m) === 1) {
                foreach (preg_split('/\s+/', trim($m[1])) ?: [] as $uid) {
                    if ($uid !== '') {
                        $uids[] = (int)$uid;
                    }
                }
            }
        }
        return $uids;
    }

    /**
     * Marks the messages as read and moves them to $folder (in the selected
     * folder). Without MOVE: copy, flag \Deleted and, with UIDPLUS, expunge
     * just these - never a plain EXPUNGE, which would remove other mails
     * flagged as deleted.
     *
     * @param list<int> $uids
     * @throws ImapException
     */
    public function markReadAndMove(array $uids, string $folder): void {
        if ($uids === []) {
            return;
        }
        $set = implode(',', array_map('intval', $uids));
        $target = self::folderName($folder);
        $this->command("UID STORE {$set} +FLAGS.SILENT (\\Seen)");
        if ($this->hasCapability('MOVE')) {
            $this->command("UID MOVE {$set}", [$target]);
            return;
        }
        $this->command("UID COPY {$set}", [$target]);
        $this->command("UID STORE {$set} +FLAGS.SILENT (\\Deleted)");
        if ($this->hasCapability('UIDPLUS')) {
            $this->command("UID EXPUNGE {$set}");
        }
    }

    /**
     * Makes sure the folder exists (creates and subscribes it). Some servers
     * only allow folders below INBOX; then "INBOX<delimiter><name>" is used.
     *
     * @return string the folder name to use
     * @throws ImapException
     */
    public function ensureFolder(string $folder): string {
        if ($this->folderExists($folder)) {
            return $folder;
        }
        try {
            $this->command('CREATE', [self::folderName($folder)]);
        } catch (ImapException $e) {
            $delimiter = $this->delimiter();
            if ($delimiter === null || str_starts_with(strtoupper($folder), 'INBOX' . $delimiter)) {
                throw new ImapException("Der Ordner „{$folder}“ konnte nicht angelegt werden: " . $e->getMessage());
            }
            $nested = 'INBOX' . $delimiter . $folder;
            if (!$this->folderExists($nested)) {
                $this->command('CREATE', [self::folderName($nested)]);
            }
            $folder = $nested;
        }
        try {
            $this->command('SUBSCRIBE', [self::folderName($folder)]);
        } catch (ImapException) {
            // only for the folder list of mail programs
        }
        return $folder;
    }

    /**
     * @throws ImapException
     */
    private function folderExists(string $folder): bool {
        foreach ($this->command('LIST', ['', self::folderName($folder)]) as $response) {
            if (preg_match('/^\* LIST /i', $response['line']) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Hierarchy delimiter of the server, e.g. "." or "/".
     *
     * @throws ImapException
     */
    private function delimiter(): ?string {
        foreach ($this->command('LIST', ['', '']) as $response) {
            if (preg_match('/^\* LIST \([^)]*\) "(.)"/i', $response['line'], $m) === 1) {
                return $m[1];
            }
        }
        return null;
    }

    /**
     * @throws ImapException
     */
    private function hasCapability(string $name): bool {
        if ($this->capabilities === null) {
            $this->capabilities = [];
            foreach ($this->command('CAPABILITY') as $response) {
                if (preg_match('/^\* CAPABILITY (.*)$/i', $response['line'], $m) === 1) {
                    $this->capabilities = array_map('strtoupper', preg_split('/\s+/', trim($m[1])) ?: []);
                }
            }
        }
        return in_array(strtoupper($name), $this->capabilities, true);
    }

    /** Folder names are modified UTF-7 on the wire (RFC 3501). */
    private static function folderName(string $folder): string {
        return mb_convert_encoding($folder, 'UTF7-IMAP', 'UTF-8');
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
