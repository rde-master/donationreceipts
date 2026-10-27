<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\AppInfo\Application;
use OCA\DonationReceipts\Exception\ValidationException;
use OCP\IAppConfig;

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
    ];

    private const DATE_KEYS = ['exemption_date', 'statute_date'];

    /** Only set through their own services (with access checks), never from the settings form. */
    private const INTERNAL_KEYS = ['folder_unsigned_id', 'folder_signed_id'];

    public function __construct(
        private IAppConfig $appConfig,
    ) {
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
        return match ($def['type']) {
            'bool' => $this->appConfig->getValueBool(Application::APP_ID, $key, $def['default']),
            'int' => $this->appConfig->getValueInt(Application::APP_ID, $key, $def['default']),
            default => $this->appConfig->getValueString(Application::APP_ID, $key, $def['default']),
        };
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
            return $value;
        }
        if (!is_string($value)) {
            throw new ValidationException('Ungültiger Wert für ' . $key);
        }
        $value = trim($value);
        if ($key === 'tax_mode' && !in_array($value, [self::TAX_MODE_EXEMPTION, self::TAX_MODE_STATUTE], true)) {
            throw new ValidationException('Ungültige Art der Steuerbegünstigung');
        }
        if (in_array($key, self::DATE_KEYS, true) && $value !== '' && !GermanFormat::isIsoDate($value)) {
            throw new ValidationException('Ungültiges Datum für ' . $key);
        }
        return $value;
    }
}
