<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Db\InquiryMapper;
use OCA\DonationReceipts\Db\Sponsor;
use OCA\DonationReceipts\Db\SponsorMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\SponsorService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Mail\IMailer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SponsorServiceTest extends TestCase {
    private SponsorMapper&MockObject $mapper;
    private SponsorService $service;

    protected function setUp(): void {
        $this->mapper = $this->createMock(SponsorMapper::class);
        $this->mapper->method('findByEmail')->willReturnCallback(
            static fn (string $email): array => $email === 'bekannt@example.org' ? [new Sponsor()] : []
        );
        $this->mapper->method('insert')->willReturnArgument(0);
        $mailer = $this->createMock(IMailer::class);
        $mailer->method('validateMailAddress')->willReturnCallback(static fn (string $e): bool => str_contains($e, '@'));
        $this->service = new SponsorService($this->mapper, $this->createMock(DonorMapper::class),
            $this->createMock(InquiryMapper::class), $mailer, $this->createMock(ITimeFactory::class));
    }

    private function rows(): array {
        return [
            ['line' => 2, 'name' => 'Bäckerei Korn', 'contact' => 'Anna Korn', 'email' => 'info@korn.example', 'noContact' => false],
            ['line' => 3, 'name' => 'Schon da GmbH', 'email' => 'Bekannt@example.org'],
            ['line' => 4, 'name' => '', 'email' => 'leer@example.org'],
            ['line' => 5, 'name' => 'Ohne Mail'],
            ['line' => 6, 'name' => 'Korn doppelt', 'email' => 'INFO@korn.example'],
        ];
    }

    public function testPreviewChecksRowsWithoutSaving(): void {
        $this->mapper->expects($this->never())->method('insert');

        $results = $this->service->importRows($this->rows(), true, 'kasse');

        $this->assertSame(['new', 'duplicate', 'error', 'error', 'duplicate'], array_column($results, 'status'));
        $this->assertSame([2, 3, 4, 5, 6], array_column($results, 'line'));
        $this->assertStringContainsString('Name', (string)$results[2]['error']);
    }

    public function testImportCreatesOnlyNewRows(): void {
        $this->mapper->expects($this->once())->method('insert')
            ->with($this->callback(static fn (Sponsor $s): bool => $s->getName() === 'Bäckerei Korn' && $s->getContact() === 'Anna Korn' && !$s->isNoContact()))
            ->willReturnArgument(0);

        $results = $this->service->importRows($this->rows(), false, 'kasse');

        $this->assertSame('created', $results[0]['status']);
    }

    public function testTooManyRowsAreRejected(): void {
        $this->expectException(ValidationException::class);
        $this->service->importRows(array_fill(0, 2001, []), true, 'kasse');
    }
}
