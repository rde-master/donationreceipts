<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Receipt>
 */
class ReceiptMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'dr_receipts', Receipt::class);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function find(int $id): Receipt {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * @return list<Receipt> newest number first
     */
    public function findAll(?int $year = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName());
        if ($year !== null) {
            $qb->where($qb->expr()->like('number', $qb->createNamedParameter($year . '-%')));
        }
        $qb->orderBy('number', 'DESC');
        return $this->findEntities($qb);
    }

    /**
     * @return list<Receipt> newest number first
     */
    public function findByDonor(int $donorId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('donor_id', $qb->createNamedParameter($donorId, IQueryBuilder::PARAM_INT)))
            ->orderBy('number', 'DESC');
        return $this->findEntities($qb);
    }

    public function countByDonor(int $donorId): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('*', 'n'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('donor_id', $qb->createNamedParameter($donorId, IQueryBuilder::PARAM_INT)));
        $result = $qb->executeQuery();
        $count = (int)$result->fetchOne();
        $result->closeCursor();
        return $count;
    }
}
