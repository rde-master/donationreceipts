<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Reply>
 */
class ReplyMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'dr_replies', Reply::class);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function find(int $id): Reply {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    public function existsByMessageId(string $messageId): bool {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('message_id', $qb->createNamedParameter($messageId)))
            ->setMaxResults(1);
        $result = $qb->executeQuery();
        $exists = $result->fetchOne() !== false;
        $result->closeCursor();
        return $exists;
    }

    /**
     * @return list<Reply> newest first
     */
    public function findAll(?string $status = null, ?int $campaignId = null): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName());
        if ($status !== null) {
            $qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($status)));
        }
        if ($campaignId !== null) {
            $qb->andWhere($qb->expr()->eq('campaign_id', $qb->createNamedParameter($campaignId, IQueryBuilder::PARAM_INT)));
        }
        $qb->orderBy('received_at', 'DESC');
        return $this->findEntities($qb);
    }

    public function countOpen(): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select($qb->func()->count('*', 'n'))
            ->from($this->getTableName())
            ->where($qb->expr()->eq('status', $qb->createNamedParameter(Reply::STATUS_OPEN)));
        $result = $qb->executeQuery();
        $count = (int)$result->fetchOne();
        $result->closeCursor();
        return $count;
    }
}
