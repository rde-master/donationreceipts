<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Mail;

use OCA\DonationReceipts\Mail\ImapClient;
use OCA\DonationReceipts\Mail\ImapException;
use OCA\DonationReceipts\Mail\MimeMessage;
use PHPUnit\Framework\TestCase;

class ImapAndMimeTest extends TestCase {
    /** @var resource */
    private $server;

    /**
     * A client talking to a fake server whose answers are queued up front.
     */
    private function client(string $serverSays): ImapClient {
        [$client, $this->server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($this->server, $serverSays);
        return new ImapClient($client);
    }

    private function sentByClient(): string {
        stream_set_blocking($this->server, false);
        return (string)stream_get_contents($this->server);
    }

    public function testExamineSearchAndFetchWithLiteral(): void {
        $message = "From: Max <max@example.org>\r\nSubject: Re: Sommerfest\r\n\r\nGerne!\r\n";
        $client = $this->client(
            "* 3 EXISTS\r\n* OK [UIDVALIDITY 77] ok\r\n* OK [UIDNEXT 12] ok\r\nA1 OK [READ-ONLY] EXAMINE done\r\n"
            . "* SEARCH 11\r\nA2 OK done\r\n"
            . '* 3 FETCH (UID 11 BODY[]<0> {' . strlen($message) . "}\r\n" . $message . ")\r\nA3 OK done\r\n"
        );

        $this->assertSame(['uidValidity' => 77, 'uidNext' => 12, 'exists' => 3], $client->examine('INBOX'));
        $this->assertSame([], $client->uidsAfter(11), 'n:* also returns the last message - filtered out');
        $this->assertSame($message, $client->fetch(11, 1000));
        $this->assertSame(
            "A1 EXAMINE \"INBOX\"\r\nA2 UID SEARCH UID 12:*\r\nA3 UID FETCH 11 (BODY.PEEK[]<0.1000>)\r\n",
            $this->sentByClient(),
        );
    }

    public function testLoginQuotesAndUsesLiteralForNonAscii(): void {
        $client = $this->client("+ go ahead\r\nA1 OK logged in\r\n");
        $client->login('kasse@verein.de', 'pässwort"1');
        $this->assertSame("A1 LOGIN \"kasse@verein.de\" {11}\r\npässwort\"1\r\n", $this->sentByClient());
    }

    public function testFailedCommandThrows(): void {
        $client = $this->client("A1 NO [TRYCREATE] no such mailbox\r\n");
        $this->expectException(ImapException::class);
        $client->examine('Gibtsnicht');
    }

    public function testMultipartAlternativePrefersPlainText(): void {
        $raw = "From: =?UTF-8?Q?J=C3=BCrgen_M=C3=BCller?= <Juergen@Example.org>\r\n"
            . "Subject: =?ISO-8859-1?Q?Re:_R=FCckmeldung?=\r\n"
            . "Date: Fri, 30 Oct 2026 10:00:00 +0100\r\n"
            . "In-Reply-To: <dr.5.abcdefgh.xyz@verein.de>\r\n"
            . "Content-Type: multipart/alternative;\r\n boundary=\"b1\"\r\n\r\n"
            . "--b1\r\nContent-Type: text/plain; charset=ISO-8859-1\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . "Wir sind dabei, gr=FC=DFe!\r\n"
            . "--b1\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n<p>HTML</p>\r\n--b1--\r\n";
        $mail = MimeMessage::parse($raw);

        $this->assertSame(['email' => 'juergen@example.org', 'name' => 'Jürgen Müller'], $mail->from());
        $this->assertSame('Re: Rückmeldung', $mail->header('Subject'));
        $this->assertSame('<dr.5.abcdefgh.xyz@verein.de>', $mail->rawHeader('In-Reply-To'));
        $this->assertSame('Wir sind dabei, grüße!', $mail->text());
        $this->assertSame(strtotime('2026-10-30T09:00:00Z'), $mail->date(0));
        $this->assertFalse($mail->isAutoReply());
    }

    public function testHtmlOnlyWithAttachmentAndAutoReply(): void {
        $raw = "From: info@baeckerei.example\r\nAuto-Submitted: auto-replied\r\n"
            . "Content-Type: multipart/mixed; boundary=xx\r\n\r\n"
            . "--xx\r\nContent-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode('<p>Bin im Urlaub</p><p>bis Montag</p>')) . "\r\n"
            . "--xx\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename=a.pdf\r\n\r\nJVBER\r\n--xx--\r\n";
        $mail = MimeMessage::parse($raw);

        $this->assertSame("Bin im Urlaub\n\nbis Montag", $mail->text());
        $this->assertSame('info@baeckerei.example', $mail->from()['email']);
        $this->assertTrue($mail->isAutoReply());
    }
}
