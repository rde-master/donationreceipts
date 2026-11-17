<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Erinnerungen an Sponsoren ohne Antwort bzw. mit fehlenden Angaben:
 * Zeitpunkt der letzten und Anzahl der Erinnerungen je Anschreiben.
 */
class Version001300Date20261117160000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $changed = false;

        $inquiries = $schema->getTable('dr_inquiries');
        if (!$inquiries->hasColumn('reminded_at')) {
            $inquiries->addColumn('reminded_at', Types::BIGINT, ['notnull' => false]);
            $changed = true;
        }
        if (!$inquiries->hasColumn('reminder_count')) {
            // null = no reminder yet
            $inquiries->addColumn('reminder_count', Types::INTEGER, ['notnull' => false]);
            $changed = true;
        }

        return $changed ? $schema : null;
    }
}
