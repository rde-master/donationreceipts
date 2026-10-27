<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\SettingsService;
use OCP\IAppConfig;
use OCP\Security\ICrypto;
use PHPUnit\Framework\TestCase;

class SettingsServiceTest extends TestCase {
    /** @var array<string, mixed> */
    private array $store = [];
    private SettingsService $service;

    protected function setUp(): void {
        $config = $this->createMock(IAppConfig::class);
        $get = fn (string $app, string $key, mixed $default) => $this->store[$key] ?? $default;
        $set = function (string $app, string $key, mixed $value): bool {
            $this->store[$key] = $value;
            return true;
        };
        $config->method('getValueString')->willReturnCallback($get);
        $config->method('getValueBool')->willReturnCallback($get);
        $config->method('getValueInt')->willReturnCallback($get);
        $config->method('setValueString')->willReturnCallback($set);
        $config->method('setValueBool')->willReturnCallback($set);
        $config->method('setValueInt')->willReturnCallback($set);
        $this->service = new SettingsService($config, $this->createMock(ICrypto::class));
    }

    public function testDefaults(): void {
        $all = $this->service->getAll();
        $this->assertSame('', $all['org_name']);
        $this->assertSame('exemption', $all['tax_mode']);
        $this->assertFalse($all['membership_fees_not_deductible']);
    }

    public function testUpdateTrimsStrings(): void {
        $this->service->update(['org_name' => '  Förderverein e.V. ', 'membership_fees_not_deductible' => true]);
        $this->assertSame('Förderverein e.V.', $this->service->get('org_name'));
        $this->assertTrue($this->service->get('membership_fees_not_deductible'));
    }

    public function testUnknownKeyIsRejected(): void {
        $this->expectException(ValidationException::class);
        $this->service->update(['org_nmae' => 'x']);
    }

    public function testInvalidDateIsRejected(): void {
        $this->expectException(ValidationException::class);
        $this->service->update(['exemption_date' => '2024-02-30']);
    }

    public function testInvalidTaxModeIsRejected(): void {
        $this->expectException(ValidationException::class);
        $this->service->update(['tax_mode' => 'other']);
    }

    public function testNothingIsStoredWhenOneValueIsInvalid(): void {
        try {
            $this->service->update(['org_name' => 'A', 'tax_mode' => 'other']);
        } catch (ValidationException) {
        }
        $this->assertSame('', $this->service->get('org_name'));
    }

    public function testMissingDependsOnTaxMode(): void {
        $this->assertContains('Datum des Freistellungsbescheids', $this->service->missingForReceipts());
        $this->service->update(['tax_mode' => 'statute']);
        $missing = $this->service->missingForReceipts();
        $this->assertContains('Datum des Feststellungsbescheids', $missing);
        $this->assertNotContains('Veranlagungszeitraum', $missing);
    }
}
