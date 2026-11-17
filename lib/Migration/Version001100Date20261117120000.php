<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * E-Mail-Adresse, an die der Sponsor seine Zuwendungsbestätigung haben möchte
 * (dr_inquiries.receipt_email).
 */
class Version001100Date20261117120000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $inquiries = $schema->getTable('dr_inquiries');
        if ($inquiries->hasColumn('receipt_email')) {
            return null;
        }
        // null = the donor's or sponsor's address
        $inquiries->addColumn('receipt_email', Types::STRING, ['notnull' => false, 'length' => 255]);
        return $schema;
    }
}
