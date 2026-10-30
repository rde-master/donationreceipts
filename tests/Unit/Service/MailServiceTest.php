<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Db\ReceiptMapper;
use OCA\DonationReceipts\Mail\SmtpSender;
use OCA\DonationReceipts\Service\FolderShareService;
use OCA\DonationReceipts\Service\MailService;
use OCA\DonationReceipts\Service\SettingsService;
use OCA\DonationReceipts\Service\StorageService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Mail\IMailer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MailServiceTest extends TestCase {
    private FolderShareService&MockObject $folderShares;
    private MailService $service;

    protected function setUp(): void {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('getString')->willReturn('Verein');
        $this->folderShares = $this->createMock(FolderShareService::class);
        $this->service = new MailService($settings, $this->createMock(StorageService::class), $this->createMock(DonorMapper::class),
            $this->createMock(ReceiptMapper::class), $this->createMock(IMailer::class), $this->createMock(ITimeFactory::class),
            $this->createMock(SmtpSender::class), $this->folderShares);
    }

    private function receipt(): Receipt {
        $receipt = new Receipt();
        $receipt->setNumber('2026-0001');
        $receipt->setType('money');
        $receipt->setAmountCents(1000);
        $receipt->setDonationDate('2026-10-01');
        return $receipt;
    }

    public function testFolderLinkOnlyWhenUsed(): void {
        $this->folderShares->expects($this->never())->method('link');
        $mail = $this->service->render($this->receipt(), new Donor(), 'Bescheinigung {nummer}', '<p>Danke, {verein}</p>');
        $this->assertSame('Bescheinigung 2026-0001', $mail['subject']);
        $this->assertSame('<p>Danke, Verein</p>', $mail['html']);
    }

    public function testFolderLinkAndButtonAreInserted(): void {
        $this->folderShares->expects($this->once())->method('link')->willReturn('https://cloud.example.org/s/abc');
        $mail = $this->service->render($this->receipt(), new Donor(), 'S', '<p>Alle Bescheinigungen: {ordnerlink}</p><p>{ordnerknopf}</p>');
        $this->assertStringContainsString('<a href="https://cloud.example.org/s/abc">https://cloud.example.org/s/abc</a>', $mail['html']);
        $this->assertStringContainsString('Zu Ihren Zuwendungsbestätigungen</a>', $mail['html']);
    }

    public function testTemplateIsSanitizedBeforeRendering(): void {
        $mail = $this->service->render($this->receipt(), new Donor(), 'S', '<p onclick="x()">Hallo<script>alert(1)</script></p>');
        $this->assertSame('<p>Hallo</p>', $mail['html']);
    }
}
