<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Db\ReceiptMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\NumberService;
use OCA\DonationReceipts\Service\ReceiptService;
use OCA\DonationReceipts\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReceiptServiceTest extends TestCase {
    private ReceiptMapper&MockObject $receipts;
    private NumberService&MockObject $numbers;
    private SettingsService&MockObject $settings;
    private IDBConnection&MockObject $db;
    private ReceiptService $service;

    private const MONEY = [
        'type' => 'money', 'amountCents' => 5000,
        'donationDate' => '2026-10-01', 'issueDate' => '2026-10-27', 'expenseWaiver' => true,
    ];

    protected function setUp(): void {
        $this->receipts = $this->createMock(ReceiptMapper::class);
        $this->receipts->method('insert')->willReturnArgument(0);
        $this->receipts->method('update')->willReturnArgument(0);
        $donors = $this->createMock(DonorMapper::class);
        $donors->method('find')->willReturn(new Donor());
        $this->numbers = $this->createMock(NumberService::class);
        $this->settings = $this->createMock(SettingsService::class);
        $this->settings->method('missingForReceipts')->willReturn([]);
        $this->db = $this->createMock(IDBConnection::class);
        $time = $this->createMock(ITimeFactory::class);
        // 2026-12-31 23:30 UTC = 2027-01-01 00:30 in Berlin
        $time->method('getTime')->willReturn(1798759800);
        $this->service = new ReceiptService($this->receipts, $donors, $this->numbers, $this->settings, $this->db, $time);
    }

    public function testCreateAssignsNumberOfGermanYearInTransaction(): void {
        $this->numbers->expects($this->once())->method('next')->with(2027)->willReturn('2027-0001');
        $this->db->expects($this->once())->method('beginTransaction');
        $this->db->expects($this->once())->method('commit');
        $receipt = $this->service->create(7, self::MONEY, 'kasse');
        $this->assertSame('2027-0001', $receipt->getNumber());
        $this->assertSame(7, $receipt->getDonorId());
        $this->assertTrue($receipt->getExpenseWaiver());
    }

    public function testFailedInsertRollsBack(): void {
        $this->numbers->method('next')->willReturn('2027-0001');
        $receipts = $this->createMock(ReceiptMapper::class);
        $receipts->method('insert')->willThrowException(new \RuntimeException('db down'));
        $donors = $this->createMock(DonorMapper::class);
        $donors->method('find')->willReturn(new Donor());
        $service = new ReceiptService($receipts, $donors, $this->numbers, $this->settings, $this->db, $this->createMock(ITimeFactory::class));
        $this->db->expects($this->once())->method('rollBack');
        $this->db->expects($this->never())->method('commit');
        $this->expectException(\RuntimeException::class);
        $service->create(7, self::MONEY, 'kasse');
    }

    public function testIncompleteSettingsBlockCreation(): void {
        $settings = $this->createMock(SettingsService::class);
        $settings->method('missingForReceipts')->willReturn(['Finanzamt']);
        $service = new ReceiptService($this->receipts, $this->createMock(DonorMapper::class), $this->numbers, $settings, $this->db, $this->createMock(ITimeFactory::class));
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Finanzamt');
        $service->create(7, self::MONEY, 'kasse');
    }

    public function testGoodsNeedDescriptionAndOrigin(): void {
        $goods = ['type' => 'goods', 'goodsDescription' => '', 'goodsOrigin' => 'private'] + self::MONEY;
        try {
            $this->service->create(7, $goods, 'kasse');
            $this->fail('description missing');
        } catch (ValidationException) {
        }
        $this->expectException(ValidationException::class);
        $this->service->create(7, ['goodsDescription' => 'Fahrrad', 'goodsOrigin' => 'gestohlen'] + $goods, 'kasse');
    }

    public function testSwitchingToMoneyClearsGoodsFields(): void {
        $this->numbers->method('next')->willReturn('2027-0001');
        $receipt = $this->service->create(7, ['type' => 'goods', 'goodsDescription' => 'Fahrrad', 'goodsOrigin' => 'private'] + self::MONEY, 'kasse');
        $this->receipts->method('find')->willReturn($receipt);
        $updated = $this->service->update(1, self::MONEY);
        $this->assertNull($updated->getGoodsDescription());
        $this->assertSame('2027-0001', $updated->getNumber());
    }

    public function testDonationAfterIssueDateIsRejected(): void {
        $this->expectException(ValidationException::class);
        $this->service->create(7, ['donationDate' => '2026-11-01'] + self::MONEY, 'kasse');
    }

    public function testUpdateOfSentReceiptIsMarked(): void {
        $receipt = new Receipt();
        $receipt->setSentAt(1798000000);
        $this->receipts->method('find')->willReturn($receipt);
        $this->assertTrue($this->service->update(1, self::MONEY)->getChangedAfterSent());
    }
}
