<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\Db\Campaign;
use OCA\DonationReceipts\Db\Inquiry;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Db\Sponsor;

/**
 * Texts around sponsor letters with placeholders like {anrede}: the
 * letter itself (LetterTemplate), the text above the response form and
 * the confirmation mail after a response. The defaults are plain text;
 * the mail ones become HTML via MailHtml::fromStored(). Rendering:
 * MailTemplate::render (plain text) and MailHtml::render (HTML).
 */
class SponsorTemplate {
    public const DEFAULT_LETTER_SUBJECT = 'Ihre Unterstützung für {aktion}';
    public const DEFAULT_LETTER_BODY = "{anrede},\n\n"
        . "für {aktion} suchen wir wieder Unterstützer. Wir würden uns sehr freuen, wenn Sie dabei sind.\n\n"
        . "Über den folgenden Link können Sie uns Ihre Unterstützung direkt mitteilen:\n{link}\n\n"
        . "Mit freundlichen Grüßen\n{verein}";

    public const DEFAULT_INTRO = "Vielen Dank, dass Sie {aktion} unterstützen möchten.\n\n"
        . "Mit der Eingabe Ihrer Daten wird Ihre Zuwendungsbestätigung (Spendenbescheinigung) technisch "
        . "bereits vorbereitet. Nach dem Eingang Ihrer Spende wird sie Ihnen automatisch per E-Mail zugesandt.";

    public const DEFAULT_CONFIRM_SUBJECT = 'Ihre Rückmeldung zu {aktion}';
    public const DEFAULT_CONFIRM_BODY = "{anrede},\n\n"
        . "vielen Dank für Ihre Rückmeldung. Wir haben folgende Angaben erhalten:\n\n{angaben}\n\n"
        . "Ihre Zuwendungsbestätigung ist damit vorbereitet und wird Ihnen nach dem Eingang Ihrer Spende "
        . "automatisch zugesandt. Bis dahin können Sie Ihre Angaben über diesen Link ändern:\n{link}\n\n"
        . "Mit freundlichen Grüßen\n{verein}";

    /** Placeholders of letters and the response form (shown in the UI). */
    public const PLACEHOLDERS = [
        'anrede' => 'Briefanrede des Sponsors',
        'name' => 'Name des Sponsors',
        'ansprechpartner' => 'Ansprechpartner',
        'aktion' => 'Name der Aktion',
        'link' => 'Link zum Rückmeldeformular',
        'linkknopf' => 'derselbe Link als Knopf',
        'verein' => 'Name des Vereins',
    ];

    /** Additionally available in the confirmation mail. */
    public const CONFIRM_PLACEHOLDERS = self::PLACEHOLDERS + [
        'angaben' => 'Die Angaben aus der Rückmeldung',
    ];

    public const TICKET_LABELS = [
        Inquiry::TICKETS_TWO => 'Ja, 2 Stück',
        Inquiry::TICKETS_ONE => 'Ja, 1 Stück',
        Inquiry::TICKETS_NONE => 'Nein',
        Inquiry::TICKETS_LATER => 'Ich melde mich noch',
    ];

    /**
     * "Sehr geehrte Frau Müller" if set, otherwise from the contact person.
     */
    public static function salutation(Sponsor $sponsor): string {
        $salutation = trim((string)$sponsor->getSalutation());
        if ($salutation !== '') {
            return $salutation;
        }
        $contact = trim((string)$sponsor->getContact());
        return $contact !== '' ? 'Guten Tag ' . $contact : 'Sehr geehrte Damen und Herren';
    }

    /**
     * @return array<string, string>
     */
    public static function values(Sponsor $sponsor, Campaign $campaign, string $link, string $orgName): array {
        return [
            'anrede' => self::salutation($sponsor),
            'name' => $sponsor->getName(),
            'ansprechpartner' => (string)$sponsor->getContact(),
            'aktion' => $campaign->getName(),
            'link' => $link,
            'verein' => $orgName,
        ];
    }

    /**
     * The response as plain text lines for the confirmation mail.
     */
    public static function summary(Inquiry $inquiry): string {
        $lines = [];
        $lines[] = 'Name: ' . $inquiry->getDisplayName();
        $address = trim(implode(', ', array_filter([
            (string)$inquiry->getStreet(),
            trim($inquiry->getZip() . ' ' . $inquiry->getCity()),
            (string)$inquiry->getCountry(),
        ], static fn (string $part): bool => $part !== '')));
        if ($address !== '') {
            $lines[] = 'Anschrift: ' . $address;
        }
        $amount = GermanFormat::amount((int)$inquiry->getAmountCents()) . ' €';
        if ($inquiry->getType() === Receipt::TYPE_GOODS) {
            $lines[] = 'Sachspende: ' . $inquiry->getGoodsDescription() . ' (Wert ' . $amount . ')';
        } else {
            $lines[] = 'Geldspende: ' . $amount;
        }
        $lines[] = 'Freikarten: ' . (self::TICKET_LABELS[$inquiry->getTickets()] ?? '–');
        $lines[] = 'Öffentlich bekannt geben (Logo auf Plakaten): ' . ($inquiry->getPublish() ? 'Ja' : 'Nein');
        $remarks = trim((string)$inquiry->getRemarks());
        if ($remarks !== '') {
            $lines[] = 'Hinweis: ' . $remarks;
        }
        return implode("\n", $lines);
    }
}
