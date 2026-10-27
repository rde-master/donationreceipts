<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Service\MailTemplate;
use PHPUnit\Framework\TestCase;

class MailTemplateTest extends TestCase {
    public function testDefaultTemplate(): void {
        $donor = new Donor();
        $donor->setKind('company');
        $donor->setCompany('Ärzte GmbH');
        $receipt = new Receipt();
        $receipt->setNumber('2026-0007');
        $receipt->setType('goods');
        $receipt->setAmountCents(123456);
        $receipt->setDonationDate('2026-03-01');

        $values = MailTemplate::values($receipt, $donor, 'Förderverein e.V.');
        $this->assertSame('Zuwendungsbestätigung 2026-0007', MailTemplate::render(MailTemplate::DEFAULT_SUBJECT, $values));
        $body = MailTemplate::render(MailTemplate::DEFAULT_BODY, $values);
        $this->assertStringContainsString('Guten Tag Ärzte GmbH,', $body);
        $this->assertStringContainsString('vom 01.03.2026 über 1.234,56 €', $body);
        $this->assertStringEndsWith("Förderverein e.V.", $body);
        $this->assertSame('Sachzuwendung', $values['art']);
    }

    public function testUnknownPlaceholderStays(): void {
        $this->assertSame('Hallo {vorname} X', MailTemplate::render('Hallo {vorname} {name}', ['name' => 'X']));
    }
}
