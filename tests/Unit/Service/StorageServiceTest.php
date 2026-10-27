<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Service\StorageService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StorageServiceTest extends TestCase {
    public static function names(): array {
        return [
            ['Max Müller', 'Max Müller'],
            ['Müller & Söhne GmbH', 'Müller & Söhne GmbH'],
            ['A/B: "Test" <x>', 'A-B- -Test- -x-'],
            ['  .Punkt. ', 'Punkt'],
            ['///', '-'],
            ['', 'Spender'],
        ];
    }

    #[DataProvider('names')]
    public function testFolderName(string $name, string $expected): void {
        $this->assertSame($expected, StorageService::folderName($name));
    }
}
