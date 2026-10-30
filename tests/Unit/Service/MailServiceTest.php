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
    private string $body = '';

    private function service(): MailService {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('getString')->willReturnCallback(fn (string $key): string => match ($key) {
            'mail_subject' => 'Bescheinigung {nummer}',
            'mail_body' => $this->body,
            default => 'Verein',
        });
        $this->folderShares = $this->createMock(FolderShareService::class);
        return new MailService($settings, $this->createMock(StorageService::class), $this->createMock(DonorMapper::class),
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
        $this->body = 'Danke';
        $service = $this->service();
        $this->folderShares->expects($this->never())->method('link');
        $this->assertSame('Danke', $service->render($this->receipt(), new Donor())['body']);
    }

    public function testFolderLinkIsInserted(): void {
        $this->body = "Alle Bescheinigungen: {ordnerlink}";
        $service = $this->service();
        $this->folderShares->expects($this->once())->method('link')->willReturn('https://cloud.example.org/s/abc');
        $mail = $service->render($this->receipt(), new Donor());
        $this->assertSame('Bescheinigung 2026-0001', $mail['subject']);
        $this->assertSame('Alle Bescheinigungen: https://cloud.example.org/s/abc', $mail['body']);
    }
}
