<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Db\ReceiptMapper;
use OCA\DonationReceipts\Service\ReceiptService;
use OCA\DonationReceipts\Service\SettingsService;
use OCA\DonationReceipts\Service\StatsService;
use PHPUnit\Framework\TestCase;

class StatsServiceTest extends TestCase {
    /** @var list<Receipt> */
    private array $receipts = [];
    /** @var array<string, string> */
    private array $settingValues = ['tax_mode' => 'exemption', 'exemption_date' => '2024-02-29', 'statute_date' => ''];
    private StatsService $service;

    protected function setUp(): void {
        $receiptMapper = $this->createMock(ReceiptMapper::class);
        $receiptMapper->method('findAll')->willReturnCallback(fn () => $this->receipts);
        $donorMapper = $this->createMock(DonorMapper::class);
        $donorMapper->method('search')->willReturn([
            $this->donor(1, 'Anna', 'anna@example.org'),
            $this->donor(2, 'Bernd', 'bernd@example.org'),
            $this->donor(3, 'Carla', null),
        ]);
        $receiptService = $this->createMock(ReceiptService::class);
        $receiptService->method('today')->willReturn('2026-06-15');
        $receiptService->method('nextNumber')->willReturn('2026-0004');
        $settings = $this->createMock(SettingsService::class);
        $settings->method('get')->willReturnCallback(fn (string $key) => $this->settingValues[$key]);
        $settings->method('getString')->willReturnCallback(fn (string $key) => $this->settingValues[$key]);
        $settings->method('missingForReceipts')->willReturn([]);
        $this->service = new StatsService($receiptMapper, $donorMapper, $receiptService, $settings);
    }

    private function donor(int $id, string $name, ?string $email): Donor {
        $donor = new Donor();
        $donor->setId($id);
        $donor->setKind(Donor::KIND_PERSON);
        $donor->setFirstName($name);
        $donor->setLastName('Muster');
        $donor->setEmail($email);
        return $donor;
    }

    private function receipt(string $number, int $donorId, int $cents, string $issueDate, string $type = Receipt::TYPE_MONEY, ?int $sentAt = null, bool $changed = false): Receipt {
        $receipt = new Receipt();
        $receipt->setId(count($this->receipts) + 1);
        $receipt->setNumber($number);
        $receipt->setDonorId($donorId);
        $receipt->setAmountCents($cents);
        $receipt->setIssueDate($issueDate);
        $receipt->setType($type);
        $receipt->setSentAt($sentAt);
        $receipt->setChangedAfterSent($changed);
        return $this->receipts[] = $receipt;
    }

    public function testEmptyDatabaseShowsCurrentYear(): void {
        $stats = $this->service->overview();
        $this->assertSame(2026, $stats['year']);
        $this->assertSame([['year' => 2026, 'count' => 0, 'amountCents' => 0]], $stats['years']);
        $this->assertSame(0, $stats['totals']['count']);
        $this->assertCount(12, $stats['months']);
    }

    public function testDefaultsToLatestYearWithReceipts(): void {
        $this->receipt('2025-0001', 1, 1000, '2025-03-01');
        $stats = $this->service->overview();
        $this->assertSame(2025, $stats['year']);
        $this->assertFalse($stats['partialYear']);
        // the current year is on the axis even without receipts
        $this->assertSame([2025, 2026], array_column($stats['years'], 'year'));
    }

    public function testTotalsMonthsAndNewDonors(): void {
        $this->receipt('2024-0001', 1, 5000, '2024-05-01');
        $this->receipt('2026-0001', 1, 10000, '2026-01-10');
        $this->receipt('2026-0002', 2, 2550, '2026-01-20', Receipt::TYPE_GOODS);
        $this->receipt('2026-0003', 2, 450, '2026-03-02');

        $stats = $this->service->overview(2026);
        $this->assertSame(3, $stats['totals']['count']);
        $this->assertSame(13000, $stats['totals']['amountCents']);
        $this->assertSame(10000, $stats['totals']['maxCents']);
        $this->assertSame(10450, $stats['totals']['moneyCents']);
        $this->assertSame(2550, $stats['totals']['goodsCents']);
        $this->assertSame(2, $stats['totals']['donors']);
        // Anna gave in 2024 already
        $this->assertSame(1, $stats['totals']['newDonors']);

        $this->assertSame(['month' => 1, 'moneyCount' => 1, 'moneyCents' => 10000, 'goodsCount' => 1, 'goodsCents' => 2550], $stats['months'][0]);
        $this->assertSame(450, $stats['months'][2]['moneyCents']);
        $this->assertSame([2024, 2025, 2026], array_column($stats['years'], 'year'));
        $this->assertSame(0, $stats['years'][1]['count']);
    }

    public function testRunningYearComparesWithSamePeriodOfPreviousYear(): void {
        $this->receipt('2025-0001', 1, 1000, '2025-06-15');
        $this->receipt('2025-0002', 1, 9000, '2025-06-16');
        $this->receipt('2026-0001', 1, 500, '2026-02-01');

        $stats = $this->service->overview(2026);
        $this->assertTrue($stats['partialYear']);
        $this->assertSame(1, $stats['previous']['count']);
        $this->assertSame(1000, $stats['previous']['amountCents']);

        // a finished year is compared with the whole previous year
        $this->assertSame(0, $this->service->overview(2025)['previous']['count']);
    }

    public function testSendingStateAndOpenTasks(): void {
        $this->receipt('2026-0001', 1, 100, '2026-01-01', sentAt: 1_700_000_000);
        $this->receipt('2026-0002', 1, 200, '2026-01-02', sentAt: 1_700_000_000, changed: true);
        $this->receipt('2026-0003', 2, 300, '2026-01-03');
        $this->receipt('2026-0004', 3, 400, '2026-01-04');

        $stats = $this->service->overview(2026);
        $this->assertSame(['sent' => 1, 'toSend' => 1, 'changed' => 1, 'noEmail' => 1], $stats['sending']);
        $this->assertSame(['2026-0002', '2026-0003'], array_column($stats['openTasks']['items'], 'number'));
        $this->assertSame(['changed', 'toSend'], array_column($stats['openTasks']['items'], 'state'));
        $this->assertSame(1, $stats['donorsWithoutEmail']);
    }

    public function testTopDonorsSortedByAmount(): void {
        $this->receipt('2026-0001', 1, 100, '2026-01-01');
        $this->receipt('2026-0002', 2, 300, '2026-01-02');
        $this->receipt('2026-0003', 1, 50, '2026-01-03');

        $top = $this->service->overview(2026)['topDonors'];
        $this->assertSame(['Bernd Muster', 'Anna Muster'], array_column($top['items'], 'name'));
        $this->assertSame([300, 150], array_column($top['items'], 'amountCents'));
        $this->assertSame(2, $top['items'][1]['count']);
        $this->assertSame(0, $top['more']);
    }

    public function testBescheidValidity(): void {
        $this->assertSame(
            ['mode' => 'exemption', 'date' => '2024-02-29', 'validUntil' => '2029-03-01'],
            $this->service->overview()['bescheid'],
        );
        $this->settingValues = ['tax_mode' => 'statute', 'exemption_date' => '', 'statute_date' => '2025-07-01'];
        $this->assertSame('2028-07-01', $this->service->overview()['bescheid']['validUntil']);
        $this->settingValues['statute_date'] = '';
        $this->assertNull($this->service->overview()['bescheid']);
    }
}
