<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use DateTimeImmutable;
use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Db\ReceiptMapper;

/**
 * Figures for the dashboard. A club has at most a few thousand receipts,
 * so everything is aggregated in PHP from one query per table.
 *
 * The year of a receipt is the year in its number (= year of issue), the
 * same grouping the receipt list uses.
 */
class StatsService {
    public const TOP_DONORS = 8;
    public const OPEN_TASKS = 8;

    public function __construct(
        private ReceiptMapper $receipts,
        private DonorMapper $donors,
        private ReceiptService $receiptService,
        private SettingsService $settings,
    ) {
    }

    /**
     * @param int|null $year null = latest year with receipts, or the current year
     * @return array<string, mixed>
     */
    public function overview(?int $year = null): array {
        $today = $this->receiptService->today();
        $currentYear = (int)substr($today, 0, 4);

        /** @var array<int, list<Receipt>> $byYear */
        $byYear = [];
        foreach ($this->receipts->findAll() as $receipt) {
            $byYear[self::yearOf($receipt)][] = $receipt;
        }
        ksort($byYear);
        $year ??= isset($byYear[$currentYear]) || $byYear === [] ? $currentYear : array_key_last($byYear);

        $donors = [];
        foreach ($this->donors->search() as $donor) {
            $donors[$donor->getId()] = $donor;
        }

        $selected = $byYear[$year] ?? [];
        // Running year: compare with the previous year up to the same day,
        // otherwise a half-finished year always looks like a slump.
        $cutoff = $year === $currentYear ? substr($today, 5) : '12-31';
        $previous = array_values(array_filter(
            $byYear[$year - 1] ?? [],
            static fn (Receipt $r) => substr($r->getIssueDate(), 5) <= $cutoff,
        ));

        return [
            'year' => $year,
            'years' => $this->years($byYear, $currentYear),
            'today' => $today,
            'partialYear' => $year === $currentYear,
            'totals' => $this->totals($selected, $byYear, $year),
            'previous' => $this->totals($previous, $byYear, $year - 1),
            'months' => $this->months($selected),
            'sending' => $this->sending($selected, $donors),
            'openTasks' => $this->openTasks($selected, $donors),
            'topDonors' => $this->topDonors($selected, $donors),
            'donorsTotal' => count($donors),
            'donorsWithoutEmail' => count(array_filter($donors, static fn (Donor $d) => trim((string)$d->getEmail()) === '')),
            'missing' => $this->settings->missingForReceipts(),
            'bescheid' => $this->bescheid(),
            'nextNumber' => $this->receiptService->nextNumber(),
        ];
    }

    private static function yearOf(Receipt $receipt): int {
        return (int)substr($receipt->getNumber(), 0, 4);
    }

    /**
     * Every year from the first receipt to today, gaps included, so the
     * year chart has an honest time axis.
     *
     * @param array<int, list<Receipt>> $byYear
     * @return list<array{year: int, count: int, amountCents: int}>
     */
    private function years(array $byYear, int $currentYear): array {
        $first = $byYear === [] ? $currentYear : min(array_key_first($byYear), $currentYear);
        $last = $byYear === [] ? $currentYear : max(array_key_last($byYear), $currentYear);
        $years = [];
        for ($y = $first; $y <= $last; $y++) {
            $list = $byYear[$y] ?? [];
            $years[] = ['year' => $y, 'count' => count($list), 'amountCents' => self::sum($list)];
        }
        return $years;
    }

    /**
     * @param list<Receipt> $list
     * @param array<int, list<Receipt>> $byYear
     * @return array<string, int>
     */
    private function totals(array $list, array $byYear, int $year): array {
        $money = array_filter($list, static fn (Receipt $r) => $r->getType() === Receipt::TYPE_MONEY);
        $goods = array_filter($list, static fn (Receipt $r) => $r->getType() === Receipt::TYPE_GOODS);
        $donorIds = array_unique(array_map(static fn (Receipt $r) => $r->getDonorId(), $list));

        // "new" = no receipt in any earlier year
        $earlier = [];
        foreach ($byYear as $y => $receipts) {
            if ($y < $year) {
                foreach ($receipts as $r) {
                    $earlier[$r->getDonorId()] = true;
                }
            }
        }

        return [
            'count' => count($list),
            'amountCents' => self::sum($list),
            'maxCents' => max([0, ...array_map(static fn (Receipt $r) => $r->getAmountCents(), $list)]),
            'moneyCount' => count($money),
            'moneyCents' => self::sum($money),
            'goodsCount' => count($goods),
            'goodsCents' => self::sum($goods),
            'donors' => count($donorIds),
            'newDonors' => count(array_filter($donorIds, static fn (int $id) => !isset($earlier[$id]))),
        ];
    }

    /**
     * @param list<Receipt> $list
     * @return list<array<string, int>> 12 entries, January first, by date of issue
     */
    private function months(array $list): array {
        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[$m] = ['month' => $m, 'moneyCount' => 0, 'moneyCents' => 0, 'goodsCount' => 0, 'goodsCents' => 0];
        }
        foreach ($list as $r) {
            $m = (int)substr($r->getIssueDate(), 5, 2);
            if (!isset($months[$m])) {
                continue;
            }
            $prefix = $r->getType() === Receipt::TYPE_GOODS ? 'goods' : 'money';
            $months[$m][$prefix . 'Count']++;
            $months[$m][$prefix . 'Cents'] += $r->getAmountCents();
        }
        return array_values($months);
    }

    /**
     * @param list<Receipt> $list
     * @param array<int, Donor> $donors
     * @return array{sent: int, toSend: int, changed: int, noEmail: int}
     */
    private function sending(array $list, array $donors): array {
        $result = ['sent' => 0, 'toSend' => 0, 'changed' => 0, 'noEmail' => 0];
        foreach ($list as $r) {
            $result[$this->sendState($r, $donors)]++;
        }
        return $result;
    }

    /**
     * @param array<int, Donor> $donors
     * @return 'sent'|'toSend'|'changed'|'noEmail'
     */
    private function sendState(Receipt $r, array $donors): string {
        if ($r->getSentAt() !== null) {
            return $r->getChangedAfterSent() ? 'changed' : 'sent';
        }
        $email = isset($donors[$r->getDonorId()]) ? trim((string)$donors[$r->getDonorId()]->getEmail()) : '';
        return $email === '' ? 'noEmail' : 'toSend';
    }

    /**
     * Receipts that can and should still be mailed, oldest first.
     *
     * @param list<Receipt> $list
     * @param array<int, Donor> $donors
     * @return array{items: list<array<string, mixed>>, more: int}
     */
    private function openTasks(array $list, array $donors): array {
        $items = [];
        foreach ($list as $r) {
            $state = $this->sendState($r, $donors);
            if ($state === 'toSend' || $state === 'changed') {
                $items[] = [
                    'id' => $r->getId(),
                    'number' => $r->getNumber(),
                    'donorName' => isset($donors[$r->getDonorId()]) ? $donors[$r->getDonorId()]->getDisplayName() : '',
                    'amountCents' => $r->getAmountCents(),
                    'state' => $state,
                ];
            }
        }
        usort($items, static fn (array $a, array $b) => strcmp($a['number'], $b['number']));
        return [
            'items' => array_slice($items, 0, self::OPEN_TASKS),
            'more' => max(0, count($items) - self::OPEN_TASKS),
        ];
    }

    /**
     * @param list<Receipt> $list
     * @param array<int, Donor> $donors
     * @return array{items: list<array<string, mixed>>, more: int}
     */
    private function topDonors(array $list, array $donors): array {
        $byDonor = [];
        foreach ($list as $r) {
            $id = $r->getDonorId();
            $byDonor[$id] ??= ['id' => $id, 'name' => isset($donors[$id]) ? $donors[$id]->getDisplayName() : '', 'count' => 0, 'amountCents' => 0];
            $byDonor[$id]['count']++;
            $byDonor[$id]['amountCents'] += $r->getAmountCents();
        }
        usort($byDonor, static fn (array $a, array $b) => [$b['amountCents'], $a['name']] <=> [$a['amountCents'], $b['name']]);
        return [
            'items' => array_slice($byDonor, 0, self::TOP_DONORS),
            'more' => max(0, count($byDonor) - self::TOP_DONORS),
        ];
    }

    /**
     * Until when receipts may be issued on the current notice
     * (§ 63 Abs. 5 AO: Freistellungsbescheid 5, Feststellung nach § 60a 3 Jahre).
     *
     * @return array{mode: string, date: string, validUntil: string}|null
     */
    private function bescheid(): ?array {
        $statute = $this->settings->get('tax_mode') === SettingsService::TAX_MODE_STATUTE;
        $date = $this->settings->getString($statute ? 'statute_date' : 'exemption_date');
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed === false) {
            return null;
        }
        return [
            'mode' => $statute ? SettingsService::TAX_MODE_STATUTE : SettingsService::TAX_MODE_EXEMPTION,
            'date' => $date,
            'validUntil' => $parsed->modify($statute ? '+3 years' : '+5 years')->format('Y-m-d'),
        ];
    }

    /**
     * @param iterable<Receipt> $list
     */
    private static function sum(iterable $list): int {
        $sum = 0;
        foreach ($list as $r) {
            $sum += $r->getAmountCents();
        }
        return $sum;
    }
}
