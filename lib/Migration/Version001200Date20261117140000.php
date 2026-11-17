<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Status der Rückmeldung je Anschreiben (Zusage, Angaben fehlen, meldet sich
 * noch, Absage, nicht mehr anschreiben), Zeitpunkt der letzten Antwort-Mail
 * und "nicht mehr anschreiben" am möglichen Sponsor.
 */
class Version001200Date20261117140000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();
        $changed = false;

        $inquiries = $schema->getTable('dr_inquiries');
        $columns = [
            // null = no status yet ("pledged" if a response with data exists)
            'outcome' => [Types::STRING, ['notnull' => false, 'length' => 32]],
            'outcome_note' => [Types::TEXT, ['notnull' => false]],
            // user who set it; null = the sponsor via the form
            'outcome_by' => [Types::STRING, ['notnull' => false, 'length' => 64]],
            'outcome_at' => [Types::BIGINT, ['notnull' => false]],
            // receipt time of the latest reply mail (not auto-replies)
            'last_reply_at' => [Types::BIGINT, ['notnull' => false]],
        ];
        foreach ($columns as $name => [$type, $options]) {
            if (!$inquiries->hasColumn($name)) {
                $inquiries->addColumn($name, $type, $options);
                $changed = true;
            }
        }

        $sponsors = $schema->getTable('dr_sponsors');
        if (!$sponsors->hasColumn('no_contact')) {
            // Nextcloud: booleans must be nullable
            $sponsors->addColumn('no_contact', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
            $changed = true;
        }

        return $changed ? $schema : null;
    }
}
