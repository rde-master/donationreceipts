<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Mail;

use OCA\DonationReceipts\Mail\MailHtml;
use OCA\DonationReceipts\Service\MailImageStore;
use OCA\DonationReceipts\Mail\SmtpSender;
use OCA\DonationReceipts\Service\SettingsService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MailHtmlTest extends TestCase {
    private const IMAGE = '/index.php/apps/donationreceipts/api/mail-images/0123456789abcdef0123456789abcdef';

    public function testPlainTextTemplateBecomesParagraphs(): void {
        $this->assertSame(
            '<p>Guten Tag {name},</p><p>Dank &amp; Gruß<br>{verein}</p>',
            MailHtml::fromStored("Guten Tag {name},\n\nDank & Gruß\n{verein}\n"),
        );
        $this->assertSame('<p>schon HTML</p>', MailHtml::fromStored('<p>schon HTML</p>'));
    }

    public function testSanitizeKeepsEditorMarkupOnly(): void {
        $html = '<h2 style="text-align: center; color: red">Titel</h2>'
            . '<p onclick="x()">Hallo <strong>fett</strong><script>alert(1)</script> <a href="javascript:alert(1)">weg</a>'
            . ' <a href="https://example.org/?a=1&b=2" target="_blank">ok</a> <a href="{link}">Formular</a></p>'
            . '<div><span>frei</span></div><ul><li>eins</li></ul>'
            . '<img src="' . self::IMAGE . '" alt="Logo" onerror="x()"><img src="https://tracker.example/x.png">';
        $this->assertSame(
            '<h2 style="text-align: center">Titel</h2>'
            . '<p>Hallo <strong>fett</strong> weg <a href="https://example.org/?a=1&amp;b=2">ok</a> <a href="{link}">Formular</a></p>'
            . 'frei<ul><li>eins</li></ul>'
            . '<img src="' . self::IMAGE . '" alt="Logo">',
            MailHtml::sanitize($html),
        );
    }

    public function testRenderEscapesValuesAndBuildsLinks(): void {
        $html = MailHtml::render(
            '<p>{name}</p><p><a href="{link}">hier</a> {link}</p><p>{linkknopf} {fehlt}</p>',
            ['name' => "<b>Max</b>\nZeile 2", 'link' => 'https://cloud.example.org/r/a"b'],
        );
        $this->assertStringContainsString('<p>&lt;b&gt;Max&lt;/b&gt;<br>Zeile 2</p>', $html);
        $this->assertStringContainsString('<a href="https://cloud.example.org/r/a&quot;b">hier</a>', $html);
        $this->assertStringContainsString('<a href="https://cloud.example.org/r/a&quot;b">https://cloud.example.org/r/a&quot;b</a>', $html);
        $this->assertStringContainsString('Zum Rückmeldeformular</a> {fehlt}', $html);
    }

    public function testToText(): void {
        $text = MailHtml::toText('<h2>Titel</h2><p>Hallo<br>Max &amp; Co</p><ul><li>eins</li><li>zwei</li></ul>'
            . '<p><a href="https://x.org">Formular</a> und <a href="https://y.org">https://y.org</a></p>');
        $this->assertSame("Titel\n\nHallo\nMax & Co\n\n- eins\n- zwei\n\nFormular (https://x.org) und https://y.org", $text);
    }

    public function testUploadedImagesAreEmbedded(): void {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('missingForMail')->willReturn([]);
        $settings->method('getString')->willReturn('verein@example.org');
        $images = $this->createMock(MailImageStore::class);
        $images->expects($this->once())->method('get')->with('0123456789abcdef0123456789abcdef')
            ->willReturn(['content' => 'PNGDATA', 'mime' => 'image/png']);
        $sender = new SmtpSender($settings, $images, $this->createMock(LoggerInterface::class));

        $email = $sender->newEmail('', 'max@example.org', 'Betreff', '<p>Hallo</p><img src="' . self::IMAGE . '" alt="Logo"><img src="' . self::IMAGE . '" alt="">');

        $this->assertStringContainsString('<img style="max-width:100%;height:auto" src="cid:bild-0123456789abcdef0123456789abcdef"', (string)$email->getHtmlBody());
        $this->assertCount(1, $email->getAttachments());
        $this->assertSame("Hallo\n\nLogo", $email->getTextBody());
    }
}
