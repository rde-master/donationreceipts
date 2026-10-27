<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCP\DB\Exception as DbException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Nummernkreis "JJJJ-NNNN", restarting at 1 every year.
 *
 * next() must run inside the caller's transaction: the UPDATE locks the
 * year's counter row until commit, so a concurrent receipt waits instead of
 * getting the same number, and a rollback gives the number back.
 */
class NumberService {
    public function __construct(
        private IDBConnection $db,
    ) {
    }

    public static function format(int $year, int $value): string {
        return sprintf('%04d-%04d', $year, $value);
    }

    /**
     * Reserves and returns the next number of $year.
     */
    public function next(int $year): string {
        $this->ensureRow($year);

        $update = $this->db->getQueryBuilder();
        $update->update('dr_counters')
            ->set('next_value', $update->func()->add('next_value', $update->createNamedParameter(1, IQueryBuilder::PARAM_INT)))
            ->where($update->expr()->eq('year', $update->createNamedParameter($year, IQueryBuilder::PARAM_INT)))
            ->executeStatement();

        return self::format($year, $this->read($year) - 1);
    }

    /**
     * The number the next receipt of $year would get (display only).
     */
    public function peek(int $year): string {
        $value = $this->read($year);
        return self::format($year, $value === 0 ? 1 : $value);
    }

    private function ensureRow(int $year): void {
        if ($this->read($year) !== 0) {
            return;
        }
        try {
            $insert = $this->db->getQueryBuilder();
            $insert->insert('dr_counters')
                ->values([
                    'year' => $insert->createNamedParameter($year, IQueryBuilder::PARAM_INT),
                    'next_value' => $insert->createNamedParameter(1, IQueryBuilder::PARAM_INT),
                ])
                ->executeStatement();
        } catch (DbException $e) {
            // Another request created the row in the meantime - fine.
            if ($e->getReason() !== DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
                throw $e;
            }
        }
    }

    /** next_value of $year, 0 if the year has no row yet. */
    private function read(int $year): int {
        $qb = $this->db->getQueryBuilder();
        $qb->select('next_value')
            ->from('dr_counters')
            ->where($qb->expr()->eq('year', $qb->createNamedParameter($year, IQueryBuilder::PARAM_INT)));
        $result = $qb->executeQuery();
        $value = $result->fetchOne();
        $result->closeCursor();
        return $value === false ? 0 : (int)$value;
    }
}
