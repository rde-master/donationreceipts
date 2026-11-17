<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Erledigte Antwort-Mails werden im Postfach als gelesen markiert und in
 * einen Ordner verschoben: wann das geschehen ist (null = noch offen).
 */
class Version002100Date20261117180000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $replies = $schema->getTable('dr_replies');
        if ($replies->hasColumn('filed_at')) {
            return null;
        }
        $replies->addColumn('filed_at', Types::BIGINT, ['notnull' => false]);
        $replies->addIndex(['status', 'filed_at'], 'dr_replies_filing');
        return $schema;
    }
}
