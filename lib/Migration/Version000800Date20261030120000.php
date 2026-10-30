<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Sponsoren anschreiben: Sponsoren (dr_sponsors), Aktionen (dr_campaigns),
 * Anschreiben-Vorlagen (dr_letter_templates) und Anfragen mit der Rückmeldung
 * des Sponsors (dr_inquiries). Dazu der öffentliche Link auf den Ordner der
 * unterschriebenen Bescheinigungen eines Spenders (dr_donors.share_token).
 */
class Version000800Date20261030120000 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        $donors = $schema->getTable('dr_donors');
        if (!$donors->hasColumn('share_token')) {
            // token of the read-only link share on the donor's "unterschrieben" folder
            $donors->addColumn('share_token', Types::STRING, ['notnull' => false, 'length' => 32]);
        }

        if (!$schema->hasTable('dr_sponsors')) {
            $table = $schema->createTable('dr_sponsors');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            // company or person as it is addressed
            $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
            $table->addColumn('contact', Types::STRING, ['notnull' => false, 'length' => 255]);
            // letter salutation, e.g. "Sehr geehrte Frau Müller"
            $table->addColumn('salutation', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('email', Types::STRING, ['notnull' => true, 'length' => 255]);
            // the donor the receipts go to (set when linked or when a donation arrives)
            $table->addColumn('donor_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
            $table->addColumn('notes', Types::TEXT, ['notnull' => false]);
            $table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['donor_id'], 'dr_sponsors_donor');
        }

        if (!$schema->hasTable('dr_campaigns')) {
            $table = $schema->createTable('dr_campaigns');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 255]);
            // closed: the response form no longer accepts input
            $table->addColumn('closed', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
            $table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
        }

        if (!$schema->hasTable('dr_letter_templates')) {
            $table = $schema->createTable('dr_letter_templates');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $table->addColumn('name', Types::STRING, ['notnull' => true, 'length' => 128]);
            $table->addColumn('subject', Types::STRING, ['notnull' => true, 'length' => 255]);
            $table->addColumn('body', Types::TEXT, ['notnull' => false]);
            $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
        }

        if (!$schema->hasTable('dr_inquiries')) {
            $table = $schema->createTable('dr_inquiries');
            $table->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
            $table->addColumn('campaign_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            $table->addColumn('sponsor_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
            // secret part of the response link
            $table->addColumn('token', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('sent_at', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('sent_to', Types::STRING, ['notnull' => false, 'length' => 255]);
            // user whose files hold the uploaded logo
            $table->addColumn('sent_by', Types::STRING, ['notnull' => false, 'length' => 64]);

            // Rückmeldung (null until the sponsor answers)
            $table->addColumn('responded_at', Types::BIGINT, ['notnull' => false]);
            // address for the receipt: 'person' or 'company'
            $table->addColumn('kind', Types::STRING, ['notnull' => false, 'length' => 16]);
            $table->addColumn('first_name', Types::STRING, ['notnull' => false, 'length' => 128]);
            $table->addColumn('last_name', Types::STRING, ['notnull' => false, 'length' => 128]);
            $table->addColumn('company', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('street', Types::STRING, ['notnull' => false, 'length' => 255]);
            $table->addColumn('zip', Types::STRING, ['notnull' => false, 'length' => 16]);
            $table->addColumn('city', Types::STRING, ['notnull' => false, 'length' => 128]);
            $table->addColumn('country', Types::STRING, ['notnull' => false, 'length' => 64]);
            // 'money' or 'goods', as on receipts
            $table->addColumn('type', Types::STRING, ['notnull' => false, 'length' => 16]);
            $table->addColumn('amount_cents', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('goods_description', Types::TEXT, ['notnull' => false]);
            $table->addColumn('goods_origin', Types::STRING, ['notnull' => false, 'length' => 16]);
            // 'two', 'one', 'none' or 'later'
            $table->addColumn('tickets', Types::STRING, ['notnull' => false, 'length' => 16]);
            // consent to name the sponsor publicly (logo on posters)
            $table->addColumn('publish', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
            $table->addColumn('logo_file_id', Types::BIGINT, ['notnull' => false]);
            $table->addColumn('remarks', Types::TEXT, ['notnull' => false]);

            // set when the donation has arrived and the receipt was created
            $table->addColumn('receipt_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);

            $table->addColumn('created_by', Types::STRING, ['notnull' => true, 'length' => 64]);
            $table->addColumn('created_at', Types::BIGINT, ['notnull' => true]);
            $table->addColumn('updated_at', Types::BIGINT, ['notnull' => true]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['token'], 'dr_inquiries_token');
            $table->addUniqueIndex(['campaign_id', 'sponsor_id'], 'dr_inquiries_campaign_sponsor');
            $table->addIndex(['sponsor_id'], 'dr_inquiries_sponsor');
        }

        return $schema;
    }
}
