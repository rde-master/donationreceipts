<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Mail;

/**
 * Just enough MIME (RFC 2045-2047) to show a reply: decoded headers and
 * the readable text - text/plain preferred, otherwise text/html converted
 * to text. Attachments are ignored.
 */
class MimeMessage {
    /**
     * @param array<string, list<string>> $headers lower-case name => raw values (unfolded)
     */
    private function __construct(
        private array $headers,
        private string $body,
    ) {
    }

    public static function parse(string $raw): self {
        [$head, $body] = self::split($raw);
        return new self(self::parseHeaders($head), $body);
    }

    /** Decoded header (encoded words resolved), first occurrence. */
    public function header(string $name): ?string {
        $value = $this->headers[strtolower($name)][0] ?? null;
        return $value === null ? null : self::decodeHeader($value);
    }

    /** Raw header value, e.g. for Message-ID or References. */
    public function rawHeader(string $name): string {
        return implode(' ', $this->headers[strtolower($name)] ?? []);
    }

    /**
     * @return array{email: string, name: string}
     */
    public function from(): array {
        $from = (string)$this->header('From');
        if (preg_match('/^(.*)<([^>]+)>\s*$/', $from, $m) === 1) {
            return ['email' => strtolower(trim($m[2])), 'name' => trim($m[1], " \t\"'")];
        }
        return ['email' => strtolower(trim($from)), 'name' => ''];
    }

    public function date(int $fallback): int {
        $time = strtotime((string)$this->header('Date'));
        return $time === false ? $fallback : $time;
    }

    /** Auto-reply (vacation notice etc.), RFC 3834 and common headers. */
    public function isAutoReply(): bool {
        $auto = strtolower((string)$this->header('Auto-Submitted'));
        $precedence = strtolower((string)$this->header('Precedence'));
        return ($auto !== '' && $auto !== 'no')
            || in_array($precedence, ['auto_reply', 'bulk', 'junk'], true)
            || $this->header('X-Autoreply') !== null
            || $this->header('X-Autorespond') !== null;
    }

    /** The readable text of the message. */
    public function text(): string {
        $text = self::textOf($this->headers, $this->body, 0);
        $text = str_replace(["\r\n", "\r"], "\n", $text ?? '');
        return trim(preg_replace("/\n{3,}/", "\n\n", $text) ?? $text);
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private static function textOf(array $headers, string $body, int $depth): ?string {
        [$type, $params] = self::contentType($headers['content-type'][0] ?? 'text/plain');
        $disposition = strtolower($headers['content-disposition'][0] ?? '');
        if (str_starts_with($disposition, 'attachment') || $depth > 10) {
            return null;
        }

        if (str_starts_with($type, 'multipart/')) {
            $boundary = $params['boundary'] ?? '';
            if ($boundary === '') {
                return null;
            }
            $parts = [];
            foreach (preg_split('/^--' . preg_quote($boundary, '/') . '(?:--)?[ \t]*\r?$/m', $body) ?: [] as $i => $chunk) {
                if ($i === 0 || trim($chunk) === '') {
                    continue; // preamble / epilogue
                }
                [$head, $partBody] = self::split(ltrim($chunk, "\r\n"));
                $parts[] = [self::parseHeaders($head), $partBody];
            }
            // alternative: prefer plain text; mixed/related: the first readable part
            foreach ($type === 'multipart/alternative' ? ['text/plain', 'text/html', ''] : [''] as $prefer) {
                foreach ($parts as [$partHeaders, $partBody]) {
                    $partType = self::contentType($partHeaders['content-type'][0] ?? 'text/plain')[0];
                    if ($prefer !== '' && $partType !== $prefer) {
                        continue;
                    }
                    $text = self::textOf($partHeaders, $partBody, $depth + 1);
                    if ($text !== null && trim($text) !== '') {
                        return $text;
                    }
                }
            }
            return null;
        }

        if ($type !== 'text/plain' && $type !== 'text/html') {
            return null;
        }
        $encoding = strtolower(trim($headers['content-transfer-encoding'][0] ?? ''));
        $content = match ($encoding) {
            'base64' => (string)base64_decode(preg_replace('/\s+/', '', $body) ?? '', false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
        $content = self::toUtf8($content, $params['charset'] ?? 'us-ascii');
        return $type === 'text/html' ? MailHtml::toText($content) : $content;
    }

    /**
     * @return array{0: string, 1: array<string, string>} type, parameters
     */
    private static function contentType(string $value): array {
        $parts = explode(';', $value);
        $type = strtolower(trim(array_shift($parts)));
        $params = [];
        foreach ($parts as $part) {
            if (preg_match('/^\s*([a-z0-9*-]+)\s*=\s*"?([^"]*)"?\s*$/i', $part, $m) === 1) {
                $params[strtolower($m[1])] = $m[2];
            }
        }
        return [$type === '' ? 'text/plain' : $type, $params];
    }

    private static function toUtf8(string $content, string $charset): string {
        $charset = strtoupper(trim($charset));
        if ($charset === '' || $charset === 'UTF-8' || $charset === 'US-ASCII') {
            return mb_check_encoding($content, 'UTF-8') ? $content : mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }
        try {
            return mb_convert_encoding($content, 'UTF-8', $charset);
        } catch (\ValueError) {
            return mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        }
    }

    private static function decodeHeader(string $value): string {
        if (!str_contains($value, '=?')) {
            return trim($value);
        }
        // adjacent encoded words are joined without the whitespace between them
        $value = preg_replace('/(\?=)\s+(=\?)/', '$1$2', $value) ?? $value;
        $decoded = function_exists('iconv_mime_decode')
            ? iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8')
            : mb_decode_mimeheader($value);
        return trim($decoded === false ? $value : $decoded);
    }

    /**
     * @return array{0: string, 1: string} header block, body
     */
    private static function split(string $raw): array {
        $parts = preg_split('/\r?\n\r?\n/', $raw, 2);
        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function parseHeaders(string $head): array {
        $head = preg_replace('/\r?\n[ \t]+/', ' ', $head) ?? $head;
        $headers = [];
        foreach (preg_split('/\r?\n/', $head) ?: [] as $line) {
            $colon = strpos($line, ':');
            if ($colon === false || $colon === 0) {
                continue;
            }
            $headers[strtolower(trim(substr($line, 0, $colon)))][] = trim(substr($line, $colon + 1));
        }
        return $headers;
    }
}
