<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Service\AmountInWords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AmountInWordsTest extends TestCase {
    public static function amounts(): array {
        return [
            [100, 'ein Euro'],
            [1, 'null Euro ein Cent'],
            [1000, 'zehn Euro'],
            [1700, 'siebzehn Euro'],
            [2100, 'einundzwanzig Euro'],
            [5000, 'fünfzig Euro'],
            [10000, 'einhundert Euro'],
            [10100, 'einhundertein Euro'],
            [25050, 'zweihundertfünfzig Euro fünfzig Cent'],
            [99999, 'neunhundertneunundneunzig Euro neunundneunzig Cent'],
            [100000, 'eintausend Euro'],
            [123456, 'eintausendzweihundertvierunddreißig Euro sechsundfünfzig Cent'],
            [3000000, 'dreißigtausend Euro'],
            [100000000, 'eine Million Euro'],
            [250000000, 'zwei Millionen fünfhunderttausend Euro'],
            [101100100, 'eine Million elftausendein Euro'],
        ];
    }

    #[DataProvider('amounts')]
    public function testEuro(int $cents, string $expected): void {
        $this->assertSame($expected, AmountInWords::euro($cents));
    }
}
