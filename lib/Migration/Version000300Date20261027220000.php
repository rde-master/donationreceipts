<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Bescheinigungen (dr_receipts) und Nummernkreis (dr_counters).
 */
class Version000300Date20261027220000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('dr_receipts')) {
            $table = $schema->createTable('dr_receipts');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            // "2026-0001", assigned once and never changed
            $table->addColumn('number', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('donor_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            // 'money' or 'goods'
            $table->addColumn('type', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('amount_cents', Types::BIGINT, ['notnull' => true]);
            // ISO dates (YYYY-MM-DD)
            $table->addColumn('donation_date', Types::STRING, ['notnull' => true, 'length' => 10]);
            $table->addColumn('issue_date', Types::STRING, ['notnull' => true, 'length' => 10]);
            // money only
            $table->addColumn('expense_waiver', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
            // goods only
            $table->addColumn('goods_description', Types::TEXT, ['notnull' => false]);
            // 'business', 'private' or 'unknown'
            $table->addColumn('goods_origin', Types::STRING, ['notnull' => false, 'length' => 16]);
            $table->addColumn('goods_valuation_documents', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
            // Nextcloud file IDs of the two PDFs
            $table->addColumn('file_unsigned_id', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('file_signed_id', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('sent_at', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('sent_to', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('changed_after_sent', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
            $table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['number'], 'dr_receipts_number');
            $table->addIndex(['donor_id'], 'dr_receipts_donor');
        }

        if (!$schema->hasTable('dr_counters')) {
            $table = $schema->createTable('dr_counters');
            $table->addColumn('year', Types::INTEGER, ['notnull' => true, 'unsigned' => true]);
            $table->addColumn('next_value', Types::INTEGER, ['notnull' => true, 'unsigned' => true]);
            $table->setPrimaryKey(['year']);
        }

        return $schema;
    }
}
