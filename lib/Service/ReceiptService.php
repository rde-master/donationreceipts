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
        private ImageStore $images,
        private StorageService $storage,
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
     * Creates the receipt: number, database row and both PDFs in their
     * folders happen in one transaction. If filing fails, nothing is saved
     * and the number is not used up.
     *
     * @param array<string, mixed> $data
     * @throws ValidationException
     * @throws \OCP\AppFramework\Db\DoesNotExistException unknown donor
     */
    public function create(int $donorId, array $data, string $uid): Receipt {
        $this->assertReady();
        $donor = $this->donors->find($donorId);

        $receipt = new Receipt();
        $receipt->setDonorId($donorId);
        $this->apply($receipt, $data);

        $now = $this->now();
        return $this->inTransaction(function (array &$created) use ($receipt, $donor, $now, $uid): Receipt {
            $receipt->setNumber($this->numbers->next((int)$now->format('Y')));
            $receipt->setCreatedBy($uid);
            $receipt->setCreatedAt($now->getTimestamp());
            $receipt->setUpdatedAt($now->getTimestamp());
            $receipt = $this->receipts->insert($receipt);
            return $this->writeFiles($receipt, $donor, $created);
        });
    }

    /**
     * Changes everything but the number and replaces both PDFs (the old
     * ones stay in Nextcloud's version history). A receipt that was already
     * sent is marked, so the UI can offer to send it again.
     *
     * @param array<string, mixed> $data
     * @throws ValidationException
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function update(int $id, array $data): Receipt {
        $this->assertReady();
        $receipt = $this->receipts->find($id);
        $donor = $this->donors->find($receipt->getDonorId());
        $this->apply($receipt, $data);
        if ($receipt->isSent()) {
            $receipt->setChangedAfterSent(true);
        }
        $receipt->setUpdatedAt($this->time->getTime());

        return $this->inTransaction(function (array &$created) use ($receipt, $donor): Receipt {
            return $this->writeFiles($this->receipts->update($receipt), $donor, $created);
        });
    }

    /**
     * Everything that is still missing before receipts can be created.
     *
     * @return list<string>
     */
    public function missingRequirements(): array {
        $missing = $this->settings->missingForReceipts();
        if (!$this->images->exists(ImageStore::SIGNATURE)) {
            $missing[] = 'Bild der Unterschrift';
        }
        if (!$this->images->exists(ImageStore::STAMP)) {
            $missing[] = 'Bild des Stempels';
        }
        return $missing;
    }

    /**
     * @throws ValidationException
     */
    private function assertReady(): void {
        $missing = $this->missingRequirements();
        if ($missing !== []) {
            throw new ValidationException('Bitte zuerst die Einstellungen vervollständigen: ' . implode(', ', $missing));
        }
        // Fails early (before a number is drawn) if a main folder is not usable.
        foreach (StorageService::VARIANTS as $variant) {
            $this->storage->mainFolder($variant);
        }
    }

    /**
     * Runs $work in a transaction; on failure files created so far are
     * removed again and the transaction is rolled back.
     *
     * @param callable(list<\OCP\Files\File>&): Receipt $work
     */
    private function inTransaction(callable $work): Receipt {
        $created = [];
        $this->db->beginTransaction();
        try {
            $receipt = $work($created);
            $this->db->commit();
            return $receipt;
        } catch (\Throwable $e) {
            foreach ($created as $file) {
                try {
                    $file->delete();
                } catch (\Throwable) {
                }
            }
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Renders both versions, files them and stores their file IDs.
     *
     * @param list<\OCP\Files\File> $created
     */
    private function writeFiles(Receipt $receipt, Donor $donor, array &$created): Receipt {
        $settings = $this->settings->getAll();
        $signature = $this->images->tempPath(ImageStore::SIGNATURE);
        $stamp = $this->images->tempPath(ImageStore::STAMP);
        if ($signature === null || $stamp === null) {
            throw new ValidationException('Unterschrift oder Stempel fehlt');
        }

        $unsigned = (new ReceiptPdf())->render($receipt, $donor, $settings);
        $signed = (new ReceiptPdf())->render($receipt, $donor, $settings, $signature, $stamp);

        $fileUnsigned = $this->storage->store(StorageService::UNSIGNED, $donor, $receipt, $unsigned, $created);
        $fileSigned = $this->storage->store(StorageService::SIGNED, $donor, $receipt, $signed, $created);
        $receipt->setFileUnsignedId($fileUnsigned->getId());
        $receipt->setFileSignedId($fileSigned->getId());
        return $this->receipts->update($receipt);
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
