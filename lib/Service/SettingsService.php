<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\AppInfo\Application;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Mail\MailHtml;
use OCP\IAppConfig;
use OCP\Security\ICrypto;

/**
 * The app-wide settings every member of the allowed group may change
 * (Vereinsdaten, Steuerbegünstigung, Unterzeichnung, ...). Stored in the
 * app config; the schema below is the single list of known keys.
 */
class SettingsService {
    public const TAX_MODE_EXEMPTION = 'exemption';
    public const TAX_MODE_STATUTE = 'statute';

    /** @var array<string, array{type: 'string'|'bool'|'int', default: string|bool|int}> */
    private const SCHEMA = [
        // Aussteller (Zuwendungsempfänger)
        'org_name' => ['type' => 'string', 'default' => ''],
        // optionale Zeile unter dem Namen, z. B. Abteilung
        'org_function' => ['type' => 'string', 'default' => ''],
        'org_street' => ['type' => 'string', 'default' => ''],
        'org_zip' => ['type' => 'string', 'default' => ''],
        'org_city' => ['type' => 'string', 'default' => ''],

        // Steuerbegünstigung: Freistellungsbescheid oder Feststellung nach § 60a AO
        'tax_mode' => ['type' => 'string', 'default' => self::TAX_MODE_EXEMPTION],
        'tax_office' => ['type' => 'string', 'default' => ''],
        'tax_number' => ['type' => 'string', 'default' => ''],
        'exemption_date' => ['type' => 'string', 'default' => ''],
        'exemption_period' => ['type' => 'string', 'default' => ''],
        'statute_date' => ['type' => 'string', 'default' => ''],
        'purposes' => ['type' => 'string', 'default' => ''],
        // Kreuz "kein Mitgliedsbeitrag" (nur Muster Geldzuwendung)
        'membership_fees_not_deductible' => ['type' => 'bool', 'default' => false],

        // Unterzeichnung
        'sign_place' => ['type' => 'string', 'default' => ''],
        'signer_name' => ['type' => 'string', 'default' => ''],
        'signer_function' => ['type' => 'string', 'default' => ''],

        // Ablage: Nextcloud file IDs of the two main folders (set via StorageService)
        'folder_unsigned_id' => ['type' => 'int', 'default' => 0],
        'folder_signed_id' => ['type' => 'int', 'default' => 0],

        // E-Mail über eigenen SMTP-Zugang (Passwort separat, verschlüsselt)
        'smtp_host' => ['type' => 'string', 'default' => ''],
        'smtp_port' => ['type' => 'int', 'default' => 587],
        'smtp_security' => ['type' => 'string', 'default' => self::SMTP_STARTTLS],
        'smtp_user' => ['type' => 'string', 'default' => ''],
        'smtp_from_name' => ['type' => 'string', 'default' => ''],
        'smtp_from_email' => ['type' => 'string', 'default' => ''],
        'mail_subject' => ['type' => 'string', 'default' => MailTemplate::DEFAULT_SUBJECT],
        'mail_body' => ['type' => 'string', 'default' => MailTemplate::DEFAULT_BODY],

        // Sponsoren-Anschreiben über einen zweiten SMTP-Zugang (Passwort separat, verschlüsselt)
        'sponsor_smtp_host' => ['type' => 'string', 'default' => ''],
        'sponsor_smtp_port' => ['type' => 'int', 'default' => 587],
        'sponsor_smtp_security' => ['type' => 'string', 'default' => self::SMTP_STARTTLS],
        'sponsor_smtp_user' => ['type' => 'string', 'default' => ''],
        'sponsor_smtp_from_name' => ['type' => 'string', 'default' => ''],
        'sponsor_smtp_from_email' => ['type' => 'string', 'default' => ''],
        // Rückmeldeformular: Text über dem Formular und Bestätigungsmail an den Sponsor
        'response_intro' => ['type' => 'string', 'default' => SponsorTemplate::DEFAULT_INTRO],
        'confirm_subject' => ['type' => 'string', 'default' => SponsorTemplate::DEFAULT_CONFIRM_SUBJECT],
        'confirm_body' => ['type' => 'string', 'default' => SponsorTemplate::DEFAULT_CONFIRM_BODY],
        // Ablage der hochgeladenen Logos (Nextcloud file ID, set via StorageService); 0 = no upload
        'folder_logos_id' => ['type' => 'int', 'default' => 0],
    ];

    public const SMTP_STARTTLS = 'starttls';
    public const SMTP_SSL = 'ssl';
    private const SMTP_PASSWORD_KEY = 'smtp_password';

    /** The two SMTP accounts, as prefix of their setting keys. */
    public const ACCOUNT_RECEIPTS = '';
    public const ACCOUNT_SPONSORS = 'sponsor_';
    public const ACCOUNTS = [self::ACCOUNT_RECEIPTS, self::ACCOUNT_SPONSORS];

    private const DATE_KEYS = ['exemption_date', 'statute_date'];

    /** Mail texts edited in the HTML editor (older plain-text values are converted on read). */
    private const HTML_KEYS = ['mail_body', 'confirm_body'];

    /** Only set through their own services (with access checks), never from the settings form. */
    private const INTERNAL_KEYS = ['folder_unsigned_id', 'folder_signed_id', 'folder_logos_id'];

    public function __construct(
        private IAppConfig $appConfig,
        private ICrypto $crypto,
    ) {
    }

    public function setSmtpPassword(string $password, string $account = self::ACCOUNT_RECEIPTS): void {
        $this->appConfig->setValueString(Application::APP_ID, $account . self::SMTP_PASSWORD_KEY, $password === '' ? '' : $this->crypto->encrypt($password), sensitive: true);
    }

    public function getSmtpPassword(string $account = self::ACCOUNT_RECEIPTS): string {
        $stored = $this->appConfig->getValueString(Application::APP_ID, $account . self::SMTP_PASSWORD_KEY, '');
        return $stored === '' ? '' : $this->crypto->decrypt($stored);
    }

    public function hasSmtpPassword(string $account = self::ACCOUNT_RECEIPTS): bool {
        return $this->appConfig->getValueString(Application::APP_ID, $account . self::SMTP_PASSWORD_KEY, '') !== '';
    }

    /**
     * Mail settings of an SMTP account that are still missing for sending.
     *
     * @return list<string>
     */
    public function missingForMail(string $account = self::ACCOUNT_RECEIPTS): array {
        $missing = [];
        if ($this->getString($account . 'smtp_host') === '') {
            $missing[] = 'SMTP-Server';
        }
        if ($this->getString($account . 'smtp_from_email') === '') {
            $missing[] = 'Absenderadresse';
        }
        return $missing;
    }

    /**
     * @return array<string, string|bool|int>
     */
    public function getAll(): array {
        $values = [];
        foreach (self::SCHEMA as $key => $def) {
            $values[$key] = $this->get($key);
        }
        return $values;
    }

    public function get(string $key): string|bool|int {
        $def = self::SCHEMA[$key] ?? throw new \InvalidArgumentException('Unknown setting ' . $key);
        $value = match ($def['type']) {
            'bool' => $this->appConfig->getValueBool(Application::APP_ID, $key, $def['default']),
            'int' => $this->appConfig->getValueInt(Application::APP_ID, $key, $def['default']),
            default => $this->appConfig->getValueString(Application::APP_ID, $key, $def['default']),
        };
        return in_array($key, self::HTML_KEYS, true) ? MailHtml::fromStored((string)$value) : $value;
    }

    public function getString(string $key): string {
        return (string)$this->get($key);
    }

    /**
     * Applies a partial update; unknown keys are rejected so typos in the
     * frontend don't get silently lost. Internal keys are skipped unless
     * $internal is set.
     *
     * @param array<string, mixed> $values
     * @throws ValidationException
     */
    public function update(array $values, bool $internal = false): void {
        $normalized = [];
        foreach ($values as $key => $value) {
            $def = self::SCHEMA[$key] ?? throw new ValidationException('Unbekannte Einstellung: ' . $key);
            if (!$internal && in_array($key, self::INTERNAL_KEYS, true)) {
                continue;
            }
            $normalized[$key] = $this->normalize($key, $def['type'], $value);
        }
        foreach ($normalized as $key => $value) {
            match (self::SCHEMA[$key]['type']) {
                'bool' => $this->appConfig->setValueBool(Application::APP_ID, $key, $value),
                'int' => $this->appConfig->setValueInt(Application::APP_ID, $key, $value),
                default => $this->appConfig->setValueString(Application::APP_ID, $key, $value),
            };
        }
    }

    /**
     * Fields that the PDFs need but are still empty, as human-readable labels.
     *
     * @return list<string>
     */
    public function missingForReceipts(): array {
        $labels = [
            'org_name' => 'Name des Vereins',
            'org_street' => 'Straße des Vereins',
            'org_zip' => 'PLZ des Vereins',
            'org_city' => 'Ort des Vereins',
            'tax_office' => 'Finanzamt',
            'tax_number' => 'Steuernummer',
            'purposes' => 'Begünstigte Zwecke',
            'sign_place' => 'Ort der Unterzeichnung',
        ];
        if ($this->get('tax_mode') === self::TAX_MODE_STATUTE) {
            $labels['statute_date'] = 'Datum des Feststellungsbescheids';
        } else {
            $labels['exemption_date'] = 'Datum des Freistellungsbescheids';
            $labels['exemption_period'] = 'Veranlagungszeitraum';
        }
        $missing = [];
        foreach ($labels as $key => $label) {
            if (trim($this->getString($key)) === '') {
                $missing[] = $label;
            }
        }
        if ($this->get('folder_unsigned_id') === 0) {
            $missing[] = 'Hauptordner „nicht unterschrieben“';
        }
        if ($this->get('folder_signed_id') === 0) {
            $missing[] = 'Hauptordner „unterschrieben“';
        }
        return $missing;
    }

    private function normalize(string $key, string $type, mixed $value): string|bool|int {
        if ($type === 'bool') {
            if (!is_bool($value)) {
                throw new ValidationException('Ungültiger Wert für ' . $key);
            }
            return $value;
        }
        if ($type === 'int') {
            if (!is_int($value)) {
                throw new ValidationException('Ungültiger Wert für ' . $key);
            }
            if (self::smtpKey($key) === 'smtp_port' && ($value < 1 || $value > 65535)) {
                throw new ValidationException('Ungültiger SMTP-Port');
            }
            return $value;
        }
        if (!is_string($value)) {
            throw new ValidationException('Ungültiger Wert für ' . $key);
        }
        if (in_array($key, self::HTML_KEYS, true)) {
            return MailHtml::sanitize($value);
        }
        $value = trim($value);
        if ($key === 'tax_mode' && !in_array($value, [self::TAX_MODE_EXEMPTION, self::TAX_MODE_STATUTE], true)) {
            throw new ValidationException('Ungültige Art der Steuerbegünstigung');
        }
        if (self::smtpKey($key) === 'smtp_security' && !in_array($value, [self::SMTP_STARTTLS, self::SMTP_SSL], true)) {
            throw new ValidationException('Ungültige Verschlüsselung');
        }
        if (self::smtpKey($key) === 'smtp_from_email' && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Die Absenderadresse ist ungültig');
        }
        if (in_array($key, self::DATE_KEYS, true) && $value !== '' && !GermanFormat::isIsoDate($value)) {
            throw new ValidationException('Ungültiges Datum für ' . $key);
        }
        return $value;
    }

    /** 'sponsor_smtp_port' -> 'smtp_port': both accounts share their validation. */
    private static function smtpKey(string $key): string {
        return str_starts_with($key, self::ACCOUNT_SPONSORS . 'smtp_') ? substr($key, strlen(self::ACCOUNT_SPONSORS)) : $key;
    }
}
