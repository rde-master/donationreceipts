<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\DonorService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Mail\IMailer;
use PHPUnit\Framework\TestCase;

class DonorServiceTest extends TestCase {
    private DonorService $service;

    protected function setUp(): void {
        $mapper = $this->createMock(DonorMapper::class);
        $mapper->method('insert')->willReturnArgument(0);
        $mailer = $this->createMock(IMailer::class);
        $mailer->method('validateMailAddress')->willReturnCallback(
            static fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        );
        $time = $this->createMock(ITimeFactory::class);
        $time->method('getTime')->willReturn(1_800_000_000);
        $this->service = new DonorService($mapper, $mailer, $time);
    }

    public function testCreatePerson(): void {
        $donor = $this->service->create(['kind' => 'person', 'firstName' => ' Max ', 'lastName' => 'Müller', 'company' => 'ignoriert'], 'kasse');
        $this->assertSame('Max Müller', $donor->getDisplayName());
        $this->assertNull($donor->getCompany());
        $this->assertSame('kasse', $donor->getCreatedBy());
        $this->assertSame(1_800_000_000, $donor->getCreatedAt());
    }

    public function testCreateCompanyDropsPersonNames(): void {
        $donor = $this->service->create(['kind' => 'company', 'company' => 'Ärzte GmbH', 'firstName' => 'x'], 'kasse');
        $this->assertSame('Ärzte GmbH', $donor->getDisplayName());
        $this->assertNull($donor->getFirstName());
    }

    public function testPersonNeedsBothNames(): void {
        $this->expectException(ValidationException::class);
        $this->service->create(['kind' => 'person', 'firstName' => 'Max'], 'kasse');
    }

    public function testCompanyNeedsName(): void {
        $this->expectException(ValidationException::class);
        $this->service->create(['kind' => 'company'], 'kasse');
    }

    public function testInvalidEmailIsRejected(): void {
        $this->expectException(ValidationException::class);
        $this->service->create(['kind' => 'person', 'firstName' => 'A', 'lastName' => 'B', 'email' => 'kaputt'], 'kasse');
    }

    public function testAddressLinesOmitGermany(): void {
        $donor = $this->service->create([
            'kind' => 'person', 'firstName' => 'A', 'lastName' => 'B',
            'street' => 'Weg 1', 'zip' => '12345', 'city' => 'Ort', 'country' => 'Deutschland',
        ], 'kasse');
        $this->assertSame(['A B', 'Weg 1', '12345 Ort'], $donor->getAddressLines());
        $donor->setCountry('Österreich');
        $this->assertSame('Österreich', $donor->getAddressLines()[3]);
    }
}
