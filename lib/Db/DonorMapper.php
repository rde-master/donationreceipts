<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<Donor>
 */
class DonorMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'dr_donors', Donor::class);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function find(int $id): Donor {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * The donor a folder is assigned to, if any.
     */
    public function findByFolderId(int $folderId, bool $signed): ?Donor {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq($signed ? 'folder_signed_id' : 'folder_unsigned_id', $qb->createNamedParameter($folderId, IQueryBuilder::PARAM_INT)))
            ->setMaxResults(1);
        $donors = $this->findEntities($qb);
        return $donors[0] ?? null;
    }

    /**
     * All donors, optionally filtered by a search term over name, company,
     * e-mail and city. Unsorted - DonorService sorts locale-aware.
     *
     * @return list<Donor>
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
                    $qb->expr()->iLike('first_name', $like),
                    $qb->expr()->iLike('last_name', $like),
                    $qb->expr()->iLike('company', $like),
                    $qb->expr()->iLike('email', $like),
                    $qb->expr()->iLike('city', $like),
                );
            }
            // every word has to match somewhere ("max müller" finds Max Müller)
            $qb->where($qb->expr()->andX(...$or));
        }

        return $this->findEntities($qb);
    }
}
