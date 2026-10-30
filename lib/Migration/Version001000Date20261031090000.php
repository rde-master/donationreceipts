<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Antworten auf Sponsoren-Anschreiben aus dem Postfach (dr_replies, mit
 * Aufgaben-Status) und manuell erfasste Rückmeldungen (dr_inquiries.response_by).
 */
class Version001000Date20261031090000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $inquiries = $schema->getTable('dr_inquiries');
        if (!$inquiries->hasColumn('response_by')) {
            // user who entered the response in the app; null = the sponsor via the form
            $inquiries->addColumn('response_by', Types::STRING, ['notnull' => false, 'length' => 64]);
        }

        if (!$schema->hasTable('dr_replies')) {
            $table = $schema->createTable('dr_replies');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            // Message-ID of the mail, against fetching it twice
            $table->addColumn('message_id', Types::STRING, ['notnull' => true, 'length' => 255]);
            // what the mail belongs to (null if only the sender is known)
            $table->addColumn('inquiry_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
            $table->addColumn('sponsor_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
            $table->addColumn('campaign_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
            $table->addColumn('from_email', Types::STRING, ['notnull' => true, 'length' => 255]);
            $table->addColumn('from_name', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('subject', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('body', Types::TEXT, ['notnull' => false]);
            // Date header (Unix timestamp)
            $table->addColumn('received_at', Types::BIGINT, ['notnull' => true]);
            // task: 'open' until someone marks it 'done'
            $table->addColumn('status', Types::STRING, ['notnull' => true, 'length' => 16]);
            $table->addColumn('note', Types::TEXT, ['notnull' => false]);
            $table->addColumn('done_by', Types::STRING, ['notnull' => false, 'length' => 64]);
            $table->addColumn('done_at', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['message_id'], 'dr_replies_message_id');
            $table->addIndex(['status'], 'dr_replies_status');
            $table->addIndex(['inquiry_id'], 'dr_replies_inquiry');
        }

        return $schema;
    }
}
