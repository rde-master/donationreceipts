<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

/**
 * Betrag in Buchstaben, e.g. 123456 cents ->
 * "eintausendzweihundertvierunddreißig Euro sechsundfünfzig Cent".
 */
class AmountInWords {
    private const ONES = ['null', 'ein', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun',
        'zehn', 'elf', 'zwölf', 'dreizehn', 'vierzehn', 'fünfzehn', 'sechzehn', 'siebzehn', 'achtzehn', 'neunzehn'];
    private const TENS = [2 => 'zwanzig', 3 => 'dreißig', 4 => 'vierzig', 5 => 'fünfzig',
        6 => 'sechzig', 7 => 'siebzig', 8 => 'achtzig', 9 => 'neunzig'];

    public static function euro(int $cents): string {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Negative amount');
        }
        $euro = intdiv($cents, 100);
        $cent = $cents % 100;

        $text = self::number($euro) . ' Euro';
        if ($cent > 0) {
            $text .= ' ' . self::number($cent) . ' Cent';
        }
        return $text;
    }

    /**
     * Cardinal number as used before a noun ("ein Euro", "einundzwanzig").
     */
    public static function number(int $n): string {
        if ($n === 0) {
            return 'null';
        }
        $parts = [];
        $millions = intdiv($n, 1_000_000);
        $n %= 1_000_000;
        if ($millions > 0) {
            $parts[] = $millions === 1
                ? 'eine Million'
                : self::belowThousand($millions) . ' Millionen';
        }
        $thousands = intdiv($n, 1000);
        $n %= 1000;
        $rest = '';
        if ($thousands > 0) {
            $rest .= self::belowThousand($thousands) . 'tausend';
        }
        if ($n > 0) {
            $rest .= self::belowThousand($n);
        }
        if ($rest !== '') {
            $parts[] = $rest;
        }
        return implode(' ', $parts);
    }

    private static function belowThousand(int $n): string {
        $text = '';
        $hundreds = intdiv($n, 100);
        $n %= 100;
        if ($hundreds > 0) {
            $text .= self::ONES[$hundreds] . 'hundert';
        }
        if ($n === 0) {
            return $text;
        }
        if ($n < 20) {
            return $text . self::ONES[$n];
        }
        $ones = $n % 10;
        $tens = self::TENS[intdiv($n, 10)];
        return $text . ($ones > 0 ? self::ONES[$ones] . 'und' . $tens : $tens);
    }
}
