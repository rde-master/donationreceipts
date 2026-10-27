<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Spender (dr_donors).
 */
class Version000200Date20261027210000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('dr_donors')) {
            $table = $schema->createTable('dr_donors');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            // 'person' or 'company'
            $table->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('first_name', Types::STRING, ['notnull' => false, 'length' => 128]);
            $table->addColumn('last_name', Types::STRING, ['notnull' => false, 'length' => 128]);
            $table->addColumn('company', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('street', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('zip', Types::STRING, ['notnull' => false, 'length' => 16]);
            $table->addColumn('city', Types::STRING, ['notnull' => false, 'length' => 128]);
            $table->addColumn('country', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('email', Types::STRING, ['notnull' => false, 'length' => 255]);
            // Nextcloud file IDs of the donor's two folders (set when the first receipt is filed)
            $table->addColumn('folder_unsigned_id', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('folder_signed_id', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('notes', Types::TEXT, ['notnull' => false]);
            $table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
            // Unix timestamps
            $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['last_name'], 'dr_donors_last_name');
            $table->addIndex(['company'], 'dr_donors_company');
        }

        return $schema;
    }
}
