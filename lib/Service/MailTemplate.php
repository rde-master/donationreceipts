<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\Receipt;

/**
 * Subject and text of the receipt mail with placeholders like {name}.
 */
class MailTemplate {
    public const DEFAULT_SUBJECT = 'Zuwendungsbestätigung {nummer}';
    public const DEFAULT_BODY = "Guten Tag {name},\n\n"
        . "vielen Dank für Ihre Zuwendung vom {datum} über {betrag}. Im Anhang erhalten Sie "
        . "die Zuwendungsbestätigung {nummer} für Ihre Steuererklärung.\n\n"
        . "Mit freundlichen Grüßen\n{verein}";

    /** Placeholders and what they stand for (shown in the settings). */
    public const PLACEHOLDERS = [
        'name' => 'Name des Spenders',
        'nummer' => 'Nummer der Bescheinigung',
        'betrag' => 'Betrag bzw. Wert',
        'datum' => 'Tag der Zuwendung',
        'art' => 'Geldzuwendung / Sachzuwendung',
        'verein' => 'Name des Vereins',
    ];

    /**
     * @return array<string, string>
     */
    public static function values(Receipt $receipt, Donor $donor, string $orgName): array {
        return [
            'name' => $donor->getDisplayName(),
            'nummer' => (string)$receipt->getNumber(),
            'betrag' => GermanFormat::amount($receipt->getAmountCents()) . ' €',
            'datum' => GermanFormat::date($receipt->getDonationDate()),
            'art' => $receipt->getType() === Receipt::TYPE_GOODS ? 'Sachzuwendung' : 'Geldzuwendung',
            'verein' => $orgName,
        ];
    }

    /**
     * Replaces {placeholder}; unknown placeholders stay as they are.
     *
     * @param array<string, string> $values
     */
    public static function render(string $template, array $values): string {
        return preg_replace_callback(
            '/\{([a-z]+)\}/',
            static fn (array $m): string => $values[$m[1]] ?? $m[0],
            $template,
        ) ?? $template;
    }
}
