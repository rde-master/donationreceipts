<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

/**
 * Formatting helpers for the German receipt texts.
 */
class GermanFormat {
    public static function isIsoDate(string $value): bool {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1) {
            return false;
        }
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
    }

    /** "2026-03-01" -> "01.03.2026"; anything else is returned unchanged. */
    public static function date(string $isoDate): string {
        if (!self::isIsoDate($isoDate)) {
            return $isoDate;
        }
        [$y, $m, $d] = explode('-', $isoDate);
        return $d . '.' . $m . '.' . $y;
    }

    /** 123456 -> "1.234,56" */
    public static function amount(int $cents): string {
        return number_format($cents / 100, 2, ',', '.');
    }
}
