<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Sponsor>
 */
class SponsorMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'dr_sponsors', Sponsor::class);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function find(int $id): Sponsor {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * All sponsors, optionally filtered by a search term over name, contact
     * and e-mail. Unsorted - SponsorService sorts locale-aware.
     *
     * @return list<Sponsor>
     */
    public function search(string $term = ''): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName());
        $term = trim($term);
        if ($term !== '') {
            $or = [];
            foreach (preg_split('/\s+/', $term) as $word) {
                $like = $qb->createNamedParameter('%' . $this->db->escapeLikeParameter($word) . '%');
                $or[] = $qb->expr()->orX(
                    $qb->expr()->iLike('name', $like),
                    $qb->expr()->iLike('contact', $like),
                    $qb->expr()->iLike('email', $like),
                );
            }
            $qb->where($qb->expr()->andX(...$or));
        }
        return $this->findEntities($qb);
    }

    /**
     * @return list<Sponsor>
     */
    public function findByDonor(int $donorId): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('donor_id', $qb->createNamedParameter($donorId, IQueryBuilder::PARAM_INT)));
        return $this->findEntities($qb);
    }

    /**
     * Sponsors with this e-mail address (case-insensitive).
     *
     * @return list<Sponsor>
     */
    public function findByEmail(string $email): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq($qb->func()->lower('email'), $qb->createNamedParameter(mb_strtolower(trim($email)))));
        return $this->findEntities($qb);
    }
}
