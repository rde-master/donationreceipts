<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Inquiry>
 */
class InquiryMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'dr_inquiries', Inquiry::class);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function find(int $id): Inquiry {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function findByToken(string $token): Inquiry {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('token', $qb->createNamedParameter($token)));
        return $this->findEntity($qb);
    }

    public function findByCampaignAndSponsor(int $campaignId, int $sponsorId): ?Inquiry {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)))
            ->andWhere($qb->expr()->eq('sponsor_id', $qb->createNamedParameter($sponsorId, IQueryBuilder::PARAM_INT)));
        $found = $this->findEntities($qb);
        return $found[0] ?? null;
    }

    /**
     * @return list<Inquiry>
     */
    public function findByCampaign(int $campaignId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)));
        return $this->findEntities($qb);
    }

    /**
     * Responses whose donation has not arrived yet, oldest first.
     *
     * @return list<Inquiry>
     */
    public function findOpenResponses(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->isNotNull('responded_at'))
            ->andWhere($qb->expr()->isNull('receipt_id'))
            ->orderBy('responded_at', 'ASC');
        return $this->findEntities($qb);
    }

    public function countBySponsor(int $sponsorId): int {
        return $this->count('sponsor_id', $sponsorId);
    }

    public function countByCampaign(int $campaignId): int {
        return $this->count('campaign_id', $campaignId);
    }

    private function count(string $column, int $id): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('*', 'n'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq($column, $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        $result = $qb->executeQuery();
        $count = (int)$result->fetchOne();
        $result->closeCursor();
        return $count;
    }
}
