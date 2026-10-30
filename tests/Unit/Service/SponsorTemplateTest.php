<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Db\Campaign;
use OCA\DonationReceipts\Db\Inquiry;
use OCA\DonationReceipts\Db\Sponsor;
use OCA\DonationReceipts\Service\MailTemplate;
use OCA\DonationReceipts\Service\SponsorTemplate;
use PHPUnit\Framework\TestCase;

class SponsorTemplateTest extends TestCase {
    public function testSalutationFallsBackToContactAndThenToGeneric(): void {
        $sponsor = new Sponsor();
        $sponsor->setName('Bäckerei Korn');
        $this->assertSame('Sehr geehrte Damen und Herren', SponsorTemplate::salutation($sponsor));
        $sponsor->setContact('Anna Korn');
        $this->assertSame('Guten Tag Anna Korn', SponsorTemplate::salutation($sponsor));
        $sponsor->setSalutation('Sehr geehrte Frau Korn');
        $this->assertSame('Sehr geehrte Frau Korn', SponsorTemplate::salutation($sponsor));
    }

    public function testDefaultLetter(): void {
        $sponsor = new Sponsor();
        $sponsor->setName('Bäckerei Korn');
        $sponsor->setSalutation('Sehr geehrte Frau Korn');
        $campaign = new Campaign();
        $campaign->setName('Sommerfest 2027');

        $values = SponsorTemplate::values($sponsor, $campaign, 'https://cloud.example.org/r/abc', 'Förderverein e.V.');
        $this->assertSame('Ihre Unterstützung für Sommerfest 2027', MailTemplate::render(SponsorTemplate::DEFAULT_LETTER_SUBJECT, $values));
        $body = MailTemplate::render(SponsorTemplate::DEFAULT_LETTER_BODY, $values);
        $this->assertStringStartsWith('Sehr geehrte Frau Korn,', $body);
        $this->assertStringContainsString("\nhttps://cloud.example.org/r/abc\n", $body);
        $this->assertStringEndsWith('Förderverein e.V.', $body);
    }

    public function testSummaryOfGoodsResponse(): void {
        $inquiry = new Inquiry();
        $inquiry->setKind('person');
        $inquiry->setFirstName('Max');
        $inquiry->setLastName('Müller');
        $inquiry->setStreet('Weg 1');
        $inquiry->setZip('12345');
        $inquiry->setCity('Ort');
        $inquiry->setType('goods');
        $inquiry->setGoodsDescription('Tombola-Preise');
        $inquiry->setAmountCents(25000);
        $inquiry->setTickets('later');
        $inquiry->setPublish(true);

        $summary = SponsorTemplate::summary($inquiry);
        $this->assertStringContainsString('Name: Max Müller', $summary);
        $this->assertStringContainsString('Anschrift: Weg 1, 12345 Ort', $summary);
        $this->assertStringContainsString('Sachspende: Tombola-Preise (Wert 250,00 €)', $summary);
        $this->assertStringContainsString('Freikarten: Ich melde mich noch', $summary);
        $this->assertStringContainsString('(Logo auf Plakaten): Ja', $summary);
        $this->assertStringNotContainsString('Hinweis', $summary);
    }
}
