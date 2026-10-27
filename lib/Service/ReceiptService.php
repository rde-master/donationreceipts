<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use DateTimeImmutable;
use DateTimeZone;
use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Db\ReceiptMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Pdf\ReceiptPdf;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;

class ReceiptService {
    /** Receipts are German documents; the year and "today" follow German time. */
    public const TIMEZONE = 'Europe/Berlin';

    public function __construct(
        private ReceiptMapper $receipts,
        private DonorMapper $donors,
        private NumberService $numbers,
        private SettingsService $settings,
        private IDBConnection $db,
        private ITimeFactory $time,
    ) {
    }

    /**
     * @return list<Receipt>
     */
    public function list(?int $year = null, ?int $donorId = null): array {
        return $donorId !== null
            ? $this->receipts->findByDonor($donorId)
            : $this->receipts->findAll($year);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function find(int $id): Receipt {
        return $this->receipts->find($id);
    }

    public function today(): string {
        return $this->now()->format('Y-m-d');
    }

    public function nextNumber(): string {
        return $this->numbers->peek((int)$this->now()->format('Y'));
    }

    /**
     * Creates the receipt and assigns its number in one transaction.
     *
     * @param array<string, mixed> $data
     * @throws ValidationException
     * @throws \OCP\AppFramework\Db\DoesNotExistException unknown donor
     */
    public function create(int $donorId, array $data, string $uid): Receipt {
        $this->assertSettingsComplete();
        $this->donors->find($donorId);

        $receipt = new Receipt();
        $receipt->setDonorId($donorId);
        $this->apply($receipt, $data);

        $now = $this->now();
        $this->db->beginTransaction();
        try {
            $receipt->setNumber($this->numbers->next((int)$now->format('Y')));
            $receipt->setCreatedBy($uid);
            $receipt->setCreatedAt($now->getTimestamp());
            $receipt->setUpdatedAt($now->getTimestamp());
            $receipt = $this->receipts->insert($receipt);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $receipt;
    }

    /**
     * Changes everything but the number. A receipt that was already sent is
     * marked, so the UI can offer to send it again.
     *
     * @param array<string, mixed> $data
     * @throws ValidationException
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function update(int $id, array $data): Receipt {
        $this->assertSettingsComplete();
        $receipt = $this->receipts->find($id);
        $this->apply($receipt, $data);
        if ($receipt->isSent()) {
            $receipt->setChangedAfterSent(true);
        }
        $receipt->setUpdatedAt($this->time->getTime());
        return $this->receipts->update($receipt);
    }

    /**
     * PDF of the receipt, rendered from the current data and settings.
     *
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function renderPdf(Receipt $receipt, ?string $signaturePath = null, ?string $stampPath = null): string {
        $donor = $this->donors->find($receipt->getDonorId());
        return (new ReceiptPdf())->render($receipt, $donor, $this->settings->getAll(), $signaturePath, $stampPath);
    }

    /**
     * @throws ValidationException
     */
    private function assertSettingsComplete(): void {
        $missing = $this->settings->missingForReceipts();
        if ($missing !== []) {
            throw new ValidationException('Bitte zuerst die Einstellungen vervollständigen: ' . implode(', ', $missing));
        }
    }

    /**
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    private function apply(Receipt $receipt, array $data): void {
        $type = $data['type'] ?? null;
        if (!in_array($type, [Receipt::TYPE_MONEY, Receipt::TYPE_GOODS], true)) {
            throw new ValidationException('Bitte Geld- oder Sachzuwendung wählen');
        }

        $amount = $data['amountCents'] ?? null;
        if (!is_int($amount) || $amount <= 0) {
            throw new ValidationException('Der Betrag muss größer als 0 sein');
        }

        $donationDate = $data['donationDate'] ?? '';
        $issueDate = $data['issueDate'] ?? '';
        if (!is_string($donationDate) || !GermanFormat::isIsoDate($donationDate)) {
            throw new ValidationException('Der Tag der Zuwendung ist ungültig');
        }
        if (!is_string($issueDate) || !GermanFormat::isIsoDate($issueDate)) {
            throw new ValidationException('Das Ausstellungsdatum ist ungültig');
        }
        if ($donationDate > $issueDate) {
            throw new ValidationException('Der Tag der Zuwendung liegt nach dem Ausstellungsdatum');
        }

        $receipt->setType($type);
        $receipt->setAmountCents($amount);
        $receipt->setDonationDate($donationDate);
        $receipt->setIssueDate($issueDate);

        if ($type === Receipt::TYPE_MONEY) {
            $receipt->setExpenseWaiver(($data['expenseWaiver'] ?? false) === true);
            $receipt->setGoodsDescription(null);
            $receipt->setGoodsOrigin(null);
            $receipt->setGoodsValuationDocuments(false);
            return;
        }

        $description = trim((string)($data['goodsDescription'] ?? ''));
        if ($description === '') {
            throw new ValidationException('Die Bezeichnung der Sachzuwendung fehlt');
        }
        $origin = $data['goodsOrigin'] ?? '';
        if (!in_array($origin, [Receipt::ORIGIN_BUSINESS, Receipt::ORIGIN_PRIVATE, Receipt::ORIGIN_UNKNOWN], true)) {
            throw new ValidationException('Bitte die Herkunft der Sachzuwendung wählen');
        }
        $receipt->setExpenseWaiver(false);
        $receipt->setGoodsDescription($description);
        $receipt->setGoodsOrigin($origin);
        $receipt->setGoodsValuationDocuments(($data['goodsValuationDocuments'] ?? false) === true);
    }

    private function now(): DateTimeImmutable {
        return (new DateTimeImmutable('@' . $this->time->getTime()))->setTimezone(new DateTimeZone(self::TIMEZONE));
    }

    /**
     * @param list<Receipt> $receipts
     * @return array<int, Donor> donors of the given receipts by id
     */
    public function donorsOf(array $receipts): array {
        $donors = [];
        foreach ($receipts as $receipt) {
            $id = $receipt->getDonorId();
            if (!isset($donors[$id])) {
                try {
                    $donors[$id] = $this->donors->find($id);
                } catch (\OCP\AppFramework\Db\DoesNotExistException) {
                }
            }
        }
        return $donors;
    }
}
