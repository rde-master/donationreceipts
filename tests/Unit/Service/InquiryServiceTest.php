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
use OCA\DonationReceipts\Service\DonorService;
use OCA\DonationReceipts\Service\GroupNotifier;
use OCA\DonationReceipts\Service\InquiryService;
use OCA\DonationReceipts\Service\MailService;
use OCA\DonationReceipts\Service\ReceiptService;
use OCA\DonationReceipts\Service\SettingsService;
use OCA\DonationReceipts\Service\SponsorService;
use OCA\DonationReceipts\Service\StorageService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IURLGenerator;
use OCP\Mail\IMailer;
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
    private GroupNotifier&MockObject $groupNotifier;
    private Campaign $campaign;
    private Sponsor $sponsor;
    private Inquiry $inquiry;
    private InquiryService $service;
    /** @var array<string, mixed> values of SettingsService::get() */
    private array $settingValues = [];

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
            'answer_yes_label' => '',
            'answer_declined_label' => 'Leider nicht für {aktion}',
            default => '{anrede}|{link}',
        });
        $this->settings->method('get')->willReturnCallback(fn (string $key) => $this->settingValues[$key] ?? 0);
        $this->sender = $this->createMock(SmtpSender::class);
        $this->sender->method('newEmail')->willReturnCallback(
            static fn (string $account, string $to, string $subject, string $body) => (new Email())->to($to)->subject($subject)->text($body)
        );
        $this->donorService = $this->createMock(DonorService::class);
        $this->receiptService = $this->createMock(ReceiptService::class);
        $this->mailService = $this->createMock(MailService::class);
        $this->sponsorService = $this->createMock(SponsorService::class);
        $this->groupNotifier = $this->createMock(GroupNotifier::class);

        $urls = $this->createMock(IURLGenerator::class);
        $urls->method('linkToRouteAbsolute')->willReturnCallback(
            static fn (string $route, array $params) => 'https://cloud.example.org/r/' . $params['token']
        );
        $random = $this->createMock(ISecureRandom::class);
        $random->method('generate')->willReturn('NEWTOKEN000000000000000000000000');
        $mailer = $this->createMock(IMailer::class);
        $mailer->method('validateMailAddress')->willReturnCallback(static fn (string $email): bool => str_contains($email, '@'));
        $time = $this->createMock(ITimeFactory::class);
        $time->method('getTime')->willReturn(1_800_000_000);

        $this->service = new InquiryService(
            $this->inquiries, $campaigns, $sponsors, $this->createMock(DonorMapper::class),
            $this->sponsorService, $this->donorService, $this->receiptService, $this->mailService,
            $this->settings, $this->createMock(StorageService::class),
            $this->sender, $this->groupNotifier, $urls, $mailer, $random, $time, $this->createMock(LoggerInterface::class),
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

    public function testRespondNotifiesGroup(): void {
        $this->settings->method('missingForMail')->willReturn([]);
        $this->groupNotifier->expects($this->once())->method('response')->with($this->inquiry, $this->sponsor, $this->campaign, false);
        $this->service->respond(self::TOKEN, self::RESPONSE, null);
    }

    public function testManualResponseWorksInClosedCampaignWithoutNotification(): void {
        $this->campaign->setClosed(true);
        $this->inquiries->method('findByCampaignAndSponsor')->willReturn(null);
        $this->groupNotifier->expects($this->never())->method('response');
        $this->sender->expects($this->never())->method('send');

        $inquiry = $this->service->respondManually(3, 9, self::RESPONSE, false, 'kasse');

        $this->assertSame('kasse', $inquiry->getResponseBy());
        $this->assertSame('NEWTOKEN000000000000000000000000', $inquiry->getToken());
        $this->assertSame(1_800_000_000, $inquiry->getRespondedAt());
        $this->assertNull($inquiry->getSentAt());
    }

    public function testMessageReferenceNeedsMatchingToken(): void {
        $this->assertSame($this->inquiry, $this->service->findByMessageReference('<x@y> <dr.5.abcdefgh.k3j2@verein.de>'));
        $this->assertNull($this->service->findByMessageReference('<dr.5.XXXXXXXX.k3j2@verein.de>'));
        $this->assertNull($this->service->findByMessageReference('<CAF+abc@mail.gmail.com>'));
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

    public function testPublicViewPrefillsReceiptEmailWithSponsorAddress(): void {
        $this->assertSame('korn@example.org', $this->service->publicView(self::TOKEN)['response']['receiptEmail']);
    }

    public function testRespondRejectsInvalidReceiptEmail(): void {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('E-Mail-Adresse');
        $this->service->respond(self::TOKEN, ['receiptEmail' => 'keine-adresse'] + self::RESPONSE, null);
    }

    public function testReceiveSendsReceiptToAddressFromResponse(): void {
        $this->service->respond(self::TOKEN, ['receiptEmail' => ' buchhaltung@korn.example '] + self::RESPONSE, null);
        $donor = new Donor();
        $donor->setId(12);
        $donor->setEmail('buchhaltung@korn.example');
        $this->donorService->expects($this->once())->method('create')
            ->with($this->callback(static fn (array $data) => $data['email'] === 'buchhaltung@korn.example'), 'kasse')
            ->willReturn($donor);
        $receipt = new Receipt();
        $receipt->setId(77);
        $this->receiptService->method('create')->willReturn($receipt);
        $this->mailService->method('templates')->willReturn(['subject' => 'S', 'body' => 'B']);
        $this->mailService->expects($this->once())->method('sendReceipt')->with($receipt, 'buchhaltung@korn.example', 'S', 'B')->willReturn($receipt);

        $this->service->receive(5, ['donorId' => null, 'type' => 'money'], 'kasse');
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
            'incomplete' => 0, 'later' => 0, 'declined' => 0, 'repliesWithoutStatus' => 0,
        ], InquiryService::summary([$a, $b, $c]));
    }

    public function testSummaryCountsStatusWithoutAmounts(): void {
        $declined = new Inquiry();
        $declined->setOutcome(Inquiry::OUTCOME_NO_CONTACT);
        $incomplete = new Inquiry();
        $incomplete->setOutcome(Inquiry::OUTCOME_INCOMPLETE);
        $replied = new Inquiry();
        $replied->setLastReplyAt(5);
        // a pledge withdrawn after the data was entered: no amount any more
        $withdrawn = new Inquiry();
        $withdrawn->setRespondedAt(2);
        $withdrawn->setAmountCents(9000);
        $withdrawn->setOutcome(Inquiry::OUTCOME_DECLINED);

        $summary = InquiryService::summary([$declined, $incomplete, $replied, $withdrawn]);

        $this->assertSame(3, $summary['responded']);
        $this->assertSame(2, $summary['declined']);
        $this->assertSame(1, $summary['incomplete']);
        $this->assertSame(1, $summary['repliesWithoutStatus']);
        $this->assertSame(0, $summary['pledgedCents']);
    }

    public function testRespondMarksPledged(): void {
        $this->service->respond(self::TOKEN, self::RESPONSE, null);
        $this->assertSame(Inquiry::OUTCOME_PLEDGED, $this->inquiry->getOutcome());
        $this->assertNull($this->inquiry->getOutcomeBy());
    }

    public function testNoContactIsKeptOnSponsorAndLiftedAgain(): void {
        $this->inquiries->method('findByCampaignAndSponsor')->willReturn($this->inquiry);
        $this->sponsorService->expects($this->exactly(2))->method('setNoContact')
            ->willReturnCallback(function (Sponsor $sponsor, bool $noContact) use (&$calls): Sponsor {
                $calls[] = $noContact;
                return $sponsor;
            });
        $calls = [];

        $this->service->setOutcome(3, 9, Inquiry::OUTCOME_NO_CONTACT, ' per Mail ', 'kasse');
        $this->assertSame('per Mail', $this->inquiry->getOutcomeNote());
        $this->assertSame('kasse', $this->inquiry->getOutcomeBy());

        $this->service->setOutcome(3, 9, Inquiry::OUTCOME_LATER, '', 'kasse');
        $this->assertSame(Inquiry::OUTCOME_LATER, $this->inquiry->getOutcome());
        $this->assertSame([true, false], $calls);
    }

    public function testPledgeWithoutDataIsRejected(): void {
        $this->inquiries->method('findByCampaignAndSponsor')->willReturn($this->inquiry);
        $this->expectException(ValidationException::class);
        $this->service->setOutcome(3, 9, Inquiry::OUTCOME_PLEDGED, '', 'kasse');
    }

    public function testSendLettersSkipsNoContactAndDeclined(): void {
        $this->settings->method('missingForMail')->willReturn([]);
        $this->sponsor->setNoContact(true);
        $this->sender->expects($this->never())->method('sendEach');

        $results = $this->service->sendLetters(3, [9], 'Betreff', 'Text', 'kasse');
        $this->assertStringContainsString('nicht mehr angeschrieben', $results[0]['error']);

        $this->sponsor->setNoContact(false);
        $this->inquiry->setOutcome(Inquiry::OUTCOME_DECLINED);
        $this->inquiries->method('findByCampaignAndSponsor')->willReturn($this->inquiry);
        $results = $this->service->sendLetters(3, [9], 'Betreff', 'Text', 'kasse');
        $this->assertStringContainsString('abgesagt', $results[0]['error']);
        $this->assertNull($this->inquiry->getSentAt());
    }

    public function testDeclineInFormNotifiesAndConfirms(): void {
        $this->settings->method('missingForMail')->willReturn([]);
        $this->sponsorService->expects($this->once())->method('setNoContact')->with($this->sponsor, true)->willReturn($this->sponsor);
        $this->groupNotifier->expects($this->once())->method('declined');
        $this->groupNotifier->expects($this->never())->method('response');
        $this->sender->expects($this->once())->method('send');

        $view = $this->service->respond(self::TOKEN, ['answer' => 'no_contact', 'message' => ' Bitte nicht mehr '], null);

        $this->assertSame(Inquiry::OUTCOME_NO_CONTACT, $view['outcome']);
        $this->assertSame('Bitte nicht mehr', $view['outcomeNote']);
        $this->assertNull($this->inquiry->getOutcomeBy());
        $this->assertFalse($this->inquiry->hasResponse());
    }

    public function testDeclineRejectsUnknownAnswer(): void {
        $this->expectException(ValidationException::class);
        $this->service->respond(self::TOKEN, ['answer' => 'maybe'], null);
    }

    public function testPledgeAfterDeclineClearsMessage(): void {
        $this->settings->method('missingForMail')->willReturn(['SMTP-Server']);
        $this->service->respond(self::TOKEN, ['answer' => 'declined', 'message' => 'nein'], null);
        $this->service->respond(self::TOKEN, self::RESPONSE, null);
        $this->assertSame(Inquiry::OUTCOME_PLEDGED, $this->inquiry->getOutcome());
        $this->assertNull($this->inquiry->getOutcomeNote());
    }

    public function testReminderIsDueAfterTheConfiguredDays(): void {
        $this->settingValues = ['reminder_days' => 14, 'reminder_max' => 2];
        $this->inquiry->setSentAt(1_800_000_000 - 15 * 86400);

        $this->assertTrue($this->service->isReminderDue($this->inquiry, $this->sponsor, $this->campaign));

        $this->inquiry->setRemindedAt(1_800_000_000 - 3 * 86400);
        $this->assertFalse($this->service->isReminderDue($this->inquiry, $this->sponsor, $this->campaign), 'reminded recently');

        $this->inquiry->setRemindedAt(1_800_000_000 - 20 * 86400);
        $this->inquiry->setReminderCount(2);
        $this->assertFalse($this->service->isReminderDue($this->inquiry, $this->sponsor, $this->campaign), 'maximum reached');
    }

    public function testOnlyOpenAnswersGetReminders(): void {
        $this->inquiry->setSentAt(1);
        $this->assertTrue($this->service->isRemindable($this->inquiry, $this->sponsor, $this->campaign));

        $this->inquiry->setLastReplyAt(2);
        $this->assertFalse($this->service->isRemindable($this->inquiry, $this->sponsor, $this->campaign), 'reply mail without status');

        $this->inquiry->setOutcome(Inquiry::OUTCOME_INCOMPLETE);
        $this->assertTrue($this->service->isRemindable($this->inquiry, $this->sponsor, $this->campaign), 'pledged without data');

        $this->inquiry->setOutcome(Inquiry::OUTCOME_DECLINED);
        $this->assertFalse($this->service->isRemindable($this->inquiry, $this->sponsor, $this->campaign));
    }

    public function testSendRemindersUsesTemplateByStatusAndCounts(): void {
        $this->settings->method('missingForMail')->willReturn([]);
        $this->inquiry->setSentAt(1);
        $this->inquiry->setOutcome(Inquiry::OUTCOME_INCOMPLETE);
        $this->inquiries->method('findByCampaignAndSponsor')->willReturn($this->inquiry);
        $this->sender->expects($this->once())->method('sendEach')
            ->with(SettingsService::ACCOUNT_SPONSORS, $this->callback(static fn (array $emails): bool => array_keys($emails) === [9]))
            ->willReturn([9 => null]);

        $results = $this->service->sendReminders(3, [9]);

        $this->assertNull($results[0]['error']);
        $this->assertSame(1, $this->inquiry->getReminderCount());
        $this->assertSame(1_800_000_000, $this->inquiry->getRemindedAt());
    }

    public function testSendRemindersSkipsAnswered(): void {
        $this->settings->method('missingForMail')->willReturn([]);
        $this->inquiry->setSentAt(1);
        $this->inquiry->setOutcome(Inquiry::OUTCOME_LATER);
        $this->inquiries->method('findByCampaignAndSponsor')->willReturn($this->inquiry);
        $this->sender->expects($this->never())->method('sendEach');

        $results = $this->service->sendReminders(3, [9]);

        $this->assertStringContainsString('keine Erinnerung', $results[0]['error']);
    }

    public function testSponsorHistoryShowsLatestStateAndDonations(): void {
        $old = new Inquiry();
        $old->setId(1);
        $old->setSponsorId(9);
        $old->setCampaignId(2);
        $old->setCreatedAt(100);
        $old->setSentAt(100);
        $old->setRespondedAt(150);
        $old->setAmountCents(20000);
        $old->setReceiptId(7);
        $new = new Inquiry();
        $new->setId(2);
        $new->setSponsorId(9);
        $new->setCampaignId(3);
        $new->setCreatedAt(200);
        $new->setSentAt(200);
        $new->setOutcome(Inquiry::OUTCOME_DECLINED);
        $new->setOutcomeAt(250);
        $this->inquiries->method('findAll')->willReturn([$new, $old]);

        $history = $this->service->sponsorHistory()[9];

        $this->assertSame(2, $history['asked']);
        $this->assertSame(1, $history['donations']);
        $this->assertSame(20000, $history['donatedCents']);
        $this->assertSame('declined', $history['last']['state']);
        $this->assertSame(3, $history['last']['campaignId']);
        $this->assertSame(250, $history['last']['at']);
    }

    public function testAnswerTextsComeFromSettingsWithDefaultFallback(): void {
        $answers = $this->service->publicView(self::TOKEN)['answers'];
        $this->assertSame('Ja, wir unterstützen Sommerfest 2027', $answers['yes']['label']);
        $this->assertSame('Leider nicht für Sommerfest 2027', $answers['declined']['label']);
        $this->assertArrayHasKey('no_contact', $answers);
    }
}
