<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Db\Campaign;
use OCA\DonationReceipts\Db\CampaignMapper;
use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Db\Inquiry;
use OCA\DonationReceipts\Db\InquiryMapper;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Db\Sponsor;
use OCA\DonationReceipts\Db\SponsorMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Mail\SmtpSender;
use OCA\DonationReceipts\Service\AccessService;
use OCA\DonationReceipts\Service\DonorService;
use OCA\DonationReceipts\Service\InquiryService;
use OCA\DonationReceipts\Service\MailService;
use OCA\DonationReceipts\Service\ReceiptService;
use OCA\DonationReceipts\Service\SettingsService;
use OCA\DonationReceipts\Service\SponsorService;
use OCA\DonationReceipts\Service\StorageService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\Notification\IManager as INotificationManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

class InquiryServiceTest extends TestCase {
    private const TOKEN = 'abcdefghijklmnopqrstuvwxyz012345';

    private const RESPONSE = [
        'kind' => 'company', 'company' => 'Bäckerei Korn GmbH',
        'street' => 'Weg 1', 'zip' => '12345', 'city' => 'Ort',
        'type' => 'money', 'amountCents' => 50000,
        'tickets' => 'two', 'publish' => true, 'remarks' => ' bitte Logo groß ',
    ];

    private InquiryMapper&MockObject $inquiries;
    private SettingsService&MockObject $settings;
    private SmtpSender&MockObject $sender;
    private DonorService&MockObject $donorService;
    private ReceiptService&MockObject $receiptService;
    private MailService&MockObject $mailService;
    private SponsorService&MockObject $sponsorService;
    private Campaign $campaign;
    private Sponsor $sponsor;
    private Inquiry $inquiry;
    private InquiryService $service;

    protected function setUp(): void {
        $this->campaign = new Campaign();
        $this->campaign->setId(3);
        $this->campaign->setName('Sommerfest 2027');
        $this->campaign->setClosed(false);
        $this->sponsor = new Sponsor();
        $this->sponsor->setId(9);
        $this->sponsor->setName('Bäckerei Korn');
        $this->sponsor->setEmail('korn@example.org');
        $this->inquiry = new Inquiry();
        $this->inquiry->setId(5);
        $this->inquiry->setCampaignId(3);
        $this->inquiry->setSponsorId(9);
        $this->inquiry->setToken(self::TOKEN);
        $this->inquiry->setSentBy('kasse');

        $this->inquiries = $this->createMock(InquiryMapper::class);
        $this->inquiries->method('findByToken')->willReturnCallback(fn () => $this->inquiry);
        $this->inquiries->method('find')->willReturnCallback(fn () => $this->inquiry);
        $this->inquiries->method('insert')->willReturnArgument(0);
        $this->inquiries->method('update')->willReturnArgument(0);
        $campaigns = $this->createMock(CampaignMapper::class);
        $campaigns->method('find')->willReturnCallback(fn () => $this->campaign);
        $sponsors = $this->createMock(SponsorMapper::class);
        $sponsors->method('find')->willReturnCallback(fn () => $this->sponsor);

        $this->settings = $this->createMock(SettingsService::class);
        $this->settings->method('getString')->willReturnCallback(static fn (string $key): string => match ($key) {
            'org_name' => 'Förderverein e.V.',
            default => '{anrede}|{link}',
        });
        $this->settings->method('get')->willReturn(0);
        $this->sender = $this->createMock(SmtpSender::class);
        $this->sender->method('newEmail')->willReturnCallback(
            static fn (string $account, string $to, string $subject, string $body) => (new Email())->to($to)->subject($subject)->text($body)
        );
        $this->donorService = $this->createMock(DonorService::class);
        $this->receiptService = $this->createMock(ReceiptService::class);
        $this->mailService = $this->createMock(MailService::class);
        $this->sponsorService = $this->createMock(SponsorService::class);

        $urls = $this->createMock(IURLGenerator::class);
        $urls->method('linkToRouteAbsolute')->willReturnCallback(
            static fn (string $route, array $params) => 'https://cloud.example.org/r/' . $params['token']
        );
        $random = $this->createMock(ISecureRandom::class);
        $random->method('generate')->willReturn('NEWTOKEN000000000000000000000000');
        $time = $this->createMock(ITimeFactory::class);
        $time->method('getTime')->willReturn(1_800_000_000);

        $this->service = new InquiryService(
            $this->inquiries, $campaigns, $sponsors, $this->createMock(DonorMapper::class),
            $this->sponsorService, $this->donorService, $this->receiptService, $this->mailService,
            $this->settings, $this->createMock(StorageService::class), $this->createMock(AccessService::class),
            $this->sender, $this->createMock(IGroupManager::class), $this->createMock(INotificationManager::class),
            $urls, $random, $time, $this->createMock(LoggerInterface::class),
        );
    }

    public function testRespondSavesResponseAndConfirms(): void {
        $this->settings->method('missingForMail')->willReturn([]);
        $this->sender->expects($this->once())->method('send')->with(SettingsService::ACCOUNT_SPONSORS,
            $this->callback(static fn (Email $email) => $email->getTo()[0]->getAddress() === 'korn@example.org'));

        $view = $this->service->respond(self::TOKEN, self::RESPONSE, null);

        $this->assertSame(1_800_000_000, $this->inquiry->getRespondedAt());
        $this->assertSame('Bäckerei Korn GmbH', $this->inquiry->getDisplayName());
        $this->assertSame(50000, $this->inquiry->getAmountCents());
        $this->assertSame('two', $this->inquiry->getTickets());
        $this->assertTrue($this->inquiry->getPublish());
        $this->assertSame('bitte Logo groß', $this->inquiry->getRemarks());
        $this->assertNull($this->inquiry->getGoodsOrigin());
        $this->assertTrue($view['editable']);
        $this->assertNull($view['logoWarning']);
    }

    public function testRespondWithoutSponsorAccountSkipsConfirmation(): void {
        $this->settings->method('missingForMail')->willReturn(['SMTP-Server']);
        $this->sender->expects($this->never())->method('send');
        $this->service->respond(self::TOKEN, self::RESPONSE, null);
        $this->assertTrue($this->inquiry->hasResponse());
    }

    public function testRespondRequiresAddress(): void {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Anschrift');
        $this->service->respond(self::TOKEN, ['city' => ''] + self::RESPONSE, null);
    }

    public function testRespondGoodsRequiresOrigin(): void {
        $this->expectException(ValidationException::class);
        $this->service->respond(self::TOKEN, ['type' => 'goods', 'goodsDescription' => 'Preise'] + self::RESPONSE, null);
    }

    public function testRespondRejectsUnknownTicketChoice(): void {
        $this->expectException(ValidationException::class);
        $this->service->respond(self::TOKEN, ['tickets' => 'three'] + self::RESPONSE, null);
    }

    public function testRespondAfterArrivalIsRejected(): void {
        $this->inquiry->setReceiptId(77);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('bereits eingegangen');
        $this->service->respond(self::TOKEN, self::RESPONSE, null);
    }

    public function testRespondToClosedCampaignIsRejected(): void {
        $this->campaign->setClosed(true);
        $this->expectException(ValidationException::class);
        $this->service->respond(self::TOKEN, self::RESPONSE, null);
    }

    public function testSendLettersCreatesInquiryAndMarksOnlySentOnes(): void {
        $this->settings->method('missingForMail')->willReturn([]);
        $this->inquiries->method('findByCampaignAndSponsor')->willReturn(null);
        $this->sender->expects($this->once())->method('sendEach')
            ->with(SettingsService::ACCOUNT_SPONSORS, $this->callback(static fn (array $emails): bool => array_keys($emails) === [9]
                && $emails[9]->getSubject() === 'Sommerfest 2027'
                && $emails[9]->getTextBody() === 'Sehr geehrte Damen und Herren|<a href="https://cloud.example.org/r/NEWTOKEN000000000000000000000000">'
                    . 'https://cloud.example.org/r/NEWTOKEN000000000000000000000000</a>'))
            ->willReturn([9 => null]);

        $results = $this->service->sendLetters(3, [9, 9], '{aktion}', '{anrede}|{link}', 'kasse');

        $this->assertSame([['sponsorId' => 9, 'name' => 'Bäckerei Korn', 'email' => 'korn@example.org', 'error' => null]], $results);
    }

    public function testFailedLetterIsNotMarkedSent(): void {
        $this->settings->method('missingForMail')->willReturn([]);
        $this->inquiry->setSentBy(null);
        $this->inquiries->method('findByCampaignAndSponsor')->willReturn($this->inquiry);
        $this->sender->method('sendEach')->willReturn([9 => 'Verbindung abgelehnt']);

        $results = $this->service->sendLetters(3, [9], 'Betreff', 'Text', 'kasse');

        $this->assertSame('Verbindung abgelehnt', $results[0]['error']);
        $this->assertNull($this->inquiry->getSentAt());
        $this->assertNull($this->inquiry->getSentBy());
    }

    public function testSendLettersNeedsSponsorAccount(): void {
        $this->settings->method('missingForMail')->willReturn(['SMTP-Server']);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Sponsoren-Anschreiben');
        $this->service->sendLetters(3, [9], 'Betreff', 'Text', 'kasse');
    }

    public function testReceiveCreatesDonorReceiptAndSendsIt(): void {
        $this->service->respond(self::TOKEN, self::RESPONSE, null);
        $donor = new Donor();
        $donor->setId(12);
        $donor->setEmail('korn@example.org');
        $this->donorService->expects($this->once())->method('create')
            ->with($this->callback(static fn (array $data) => $data['company'] === 'Bäckerei Korn GmbH' && $data['email'] === 'korn@example.org'), 'kasse')
            ->willReturn($donor);
        $this->sponsorService->expects($this->once())->method('linkDonor')->with($this->sponsor, 12);
        $receipt = new Receipt();
        $receipt->setId(77);
        $this->receiptService->expects($this->once())->method('create')->with(12, $this->anything(), 'kasse')->willReturn($receipt);
        $this->mailService->method('templates')->willReturn(['subject' => 'S', 'body' => 'B']);
        $this->mailService->expects($this->once())->method('sendReceipt')->with($receipt, 'korn@example.org', 'S', 'B')->willReturn($receipt);

        $result = $this->service->receive(5, ['donorId' => null, 'type' => 'money'], 'kasse');

        $this->assertSame(77, $this->inquiry->getReceiptId());
        $this->assertNull($result['mailError']);
    }

    public function testReceiveKeepsReceiptWhenMailFails(): void {
        $this->service->respond(self::TOKEN, self::RESPONSE, null);
        $donor = new Donor();
        $donor->setId(12);
        $this->donorService->method('create')->willReturn($donor);
        $receipt = new Receipt();
        $receipt->setId(77);
        $this->receiptService->method('create')->willReturn($receipt);
        $this->mailService->method('templates')->willReturn(['subject' => 'S', 'body' => 'B']);
        $this->mailService->method('sendReceipt')->willThrowException(new ValidationException('Versand fehlgeschlagen: timeout'));

        $result = $this->service->receive(5, ['donorId' => null], 'kasse');

        $this->assertSame(77, $this->inquiry->getReceiptId());
        $this->assertSame('Versand fehlgeschlagen: timeout', $result['mailError']);
    }

    public function testReceiveWithInvalidReceiptDataCreatesNoDonor(): void {
        $this->service->respond(self::TOKEN, self::RESPONSE, null);
        $this->receiptService->method('validate')->willThrowException(new ValidationException('Der Tag der Zuwendung ist ungültig'));
        $this->donorService->expects($this->never())->method('create');
        $this->expectException(ValidationException::class);
        $this->service->receive(5, ['donorId' => null], 'kasse');
    }

    public function testReceiveWithoutResponseIsRejected(): void {
        $this->expectException(ValidationException::class);
        $this->service->receive(5, [], 'kasse');
    }

    public function testSummary(): void {
        $a = new Inquiry();
        $a->setSentAt(1);
        $b = new Inquiry();
        $b->setSentAt(1);
        $b->setRespondedAt(2);
        $b->setAmountCents(10000);
        $b->setTickets('two');
        $b->setPublish(true);
        $c = new Inquiry();
        $c->setSentAt(1);
        $c->setRespondedAt(2);
        $c->setAmountCents(5000);
        $c->setTickets('later');
        $c->setPublish(false);
        $c->setReceiptId(4);

        $this->assertSame([
            'sent' => 3, 'responded' => 2, 'received' => 1, 'pledgedCents' => 15000, 'openCents' => 10000,
            'tickets' => 2, 'ticketsLater' => 1, 'publish' => 1,
        ], InquiryService::summary([$a, $b, $c]));
    }
}
