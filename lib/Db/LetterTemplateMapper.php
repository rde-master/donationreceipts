<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<LetterTemplate>
 */
class LetterTemplateMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'dr_letter_templates', LetterTemplate::class);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function find(int $id): LetterTemplate {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
        return $this->findEntity($qb);
    }

    /**
     * @return list<LetterTemplate> by name
     */
    public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->orderBy('name', 'ASC');
        return $this->findEntities($qb);
    }
}
