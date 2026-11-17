<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Db\Campaign;
use OCA\DonationReceipts\Db\CampaignMapper;
use OCA\DonationReceipts\Db\Inquiry;
use OCA\DonationReceipts\Db\InquiryMapper;
use OCA\DonationReceipts\Db\Reply;
use OCA\DonationReceipts\Db\ReplyMapper;
use OCA\DonationReceipts\Db\Sponsor;
use OCA\DonationReceipts\Db\SponsorMapper;
use OCA\DonationReceipts\Mail\ImapClient;
use OCA\DonationReceipts\Mail\MimeMessage;
use OCA\DonationReceipts\Service\GroupNotifier;
use OCA\DonationReceipts\Service\InquiryService;
use OCA\DonationReceipts\Service\ReplyService;
use OCA\DonationReceipts\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAppConfig;
use OCP\ICertificateManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReplyServiceTest extends TestCase {
    private ReplyMapper&MockObject $replies;
    private SponsorMapper&MockObject $sponsors;
    private InquiryMapper&MockObject $inquiries;
    private InquiryService&MockObject $inquiryService;
    private GroupNotifier&MockObject $notifier;
    private ReplyService $service;
    private Inquiry $inquiry;

    protected function setUp(): void {
        $this->replies = $this->createMock(ReplyMapper::class);
        $this->replies->method('insert')->willReturnCallback(static function (Reply $r): Reply {
            $r->setId(1);
            return $r;
        });
        $this->sponsors = $this->createMock(SponsorMapper::class);
        $sponsor = new Sponsor();
        $sponsor->setId(9);
        $sponsor->setName('Bäckerei Korn');
        $this->sponsors->method('find')->willReturn($sponsor);
        $this->inquiries = $this->createMock(InquiryMapper::class);
        $this->inquiry = new Inquiry();
        $this->inquiry->setId(5);
        $this->inquiry->setSponsorId(9);
        $this->inquiry->setCampaignId(3);
        $campaigns = $this->createMock(CampaignMapper::class);
        $campaign = new Campaign();
        $campaign->setName('Sommerfest 2027');
        $campaigns->method('find')->willReturn($campaign);
        $this->inquiryService = $this->createMock(InquiryService::class);
        $this->notifier = $this->createMock(GroupNotifier::class);
        $settings = $this->createMock(SettingsService::class);
        $settings->method('getString')->willReturn('anschreiben@verein.de');
        $time = $this->createMock(ITimeFactory::class);
        $time->method('getTime')->willReturn(1_800_000_000);
        $this->service = new ReplyService($this->replies, $this->inquiries, $this->sponsors, $campaigns, $this->inquiryService,
            $this->notifier, $settings, $this->createMock(IAppConfig::class), $this->createMock(ICertificateManager::class),
            $time, $this->createMock(LoggerInterface::class));
    }

    private function mail(string $from, string $headers = ''): MimeMessage {
        return MimeMessage::parse("From: {$from}\r\nMessage-ID: <m1@example.org>\r\nSubject: Re: Anfrage\r\n{$headers}\r\nGerne dabei.\r\n");
    }

    public function testReplyToLetterIsAssignedAndNotified(): void {
        $this->inquiryService->method('findByMessageReference')->willReturn($this->inquiry);
        $this->notifier->expects($this->once())->method('reply')->with($this->anything(), 'Bäckerei Korn', 'Sommerfest 2027');

        $reply = $this->service->import($this->mail('Anna <anna@other.example>', "In-Reply-To: <dr.5.abcdefgh.x@verein.de>\r\n"), 'raw');

        $this->assertSame(5, $reply->getInquiryId());
        $this->assertSame(9, $reply->getSponsorId());
        $this->assertSame(3, $reply->getCampaignId());
        $this->assertSame(Reply::STATUS_OPEN, $reply->getStatus());
        $this->assertSame('Gerne dabei.', $reply->getBody());
        $this->assertSame('<m1@example.org>', $reply->getMessageId());
    }

    public function testMailFromPossibleSponsorIsAssignedToLatestInquiry(): void {
        $sponsor = new Sponsor();
        $sponsor->setId(9);
        $this->sponsors->method('findByEmail')->with('korn@example.org')->willReturn([$sponsor]);
        $this->inquiries->method('findLatestBySponsor')->with(9)->willReturn($this->inquiry);

        $reply = $this->service->import($this->mail('KORN@example.org'), 'raw');

        $this->assertSame(5, $reply->getInquiryId());
    }

    public function testUnrelatedMailIsIgnored(): void {
        $this->sponsors->method('findByEmail')->willReturn([]);
        $this->replies->expects($this->never())->method('insert');
        $this->assertNull($this->service->import($this->mail('newsletter@shop.example'), 'raw'));
    }

    public function testReplyMarksInquiryAsAnswered(): void {
        $this->inquiryService->method('findByMessageReference')->willReturn($this->inquiry);
        $this->inquiryService->expects($this->once())->method('noteReply')->with($this->inquiry, $this->anything());
        $this->service->import($this->mail('anna@other.example', "In-Reply-To: <dr.5.abcdefgh.x@verein.de>\r\n"), 'raw');
    }

    public function testAutoReplyIsNoTask(): void {
        $this->inquiryService->method('findByMessageReference')->willReturn($this->inquiry);
        $this->inquiryService->expects($this->never())->method('noteReply');
        $this->notifier->expects($this->never())->method('reply');

        $reply = $this->service->import($this->mail('anna@other.example', "Auto-Submitted: auto-replied\r\nReferences: <dr.5.abcdefgh.x@verein.de>\r\n"), 'raw');

        $this->assertSame(Reply::STATUS_DONE, $reply->getStatus());
    }

    public function testKnownMessageIsNotImportedTwice(): void {
        $this->replies->method('existsByMessageId')->willReturn(true);
        $this->replies->expects($this->never())->method('insert');
        $this->assertNull($this->service->import($this->mail('anna@other.example'), 'raw'));
    }

    public function testFirstFetchLooksAtTheLastDays(): void {
        $client = $this->createMock(ImapClient::class);
        $client->method('examine')->willReturn(['exists' => 12, 'uidValidity' => 7, 'uidNext' => 50]);
        $client->expects($this->once())->method('uidsSince')
            ->with(1_800_000_000 - 30 * 86400)->willReturn([40, 45]);
        $client->expects($this->once())->method('uidsAfter')->with(39)->willReturn([40, 45]);
        $client->expects($this->exactly(2))->method('fetch')->willReturn(null);

        $store = [];
        $appConfig = $this->createMock(IAppConfig::class);
        $appConfig->method('setValueInt')->willReturnCallback(static function (string $app, string $key, int $value) use (&$store): bool {
            $store[$key] = $value;
            return true;
        });
        $appConfig->method('getValueInt')->willReturnCallback(static function (string $app, string $key, int $default) use (&$store): int {
            return $store[$key] ?? $default;
        });
        $settings = $this->createMock(SettingsService::class);
        $settings->method('missingForImap')->willReturn([]);
        $settings->method('imapAccount')->willReturn(['host' => 'h', 'port' => 993, 'ssl' => true, 'user' => 'u', 'password' => 'p', 'folder' => 'INBOX']);
        $time = $this->createMock(ITimeFactory::class);
        $time->method('getTime')->willReturn(1_800_000_000);

        $service = new class($client, $this->replies, $this->inquiries, $this->sponsors, $this->createMock(CampaignMapper::class), $this->inquiryService, $this->notifier, $settings, $appConfig, $this->createMock(ICertificateManager::class), $time, $this->createMock(LoggerInterface::class)) extends ReplyService {
            public function __construct(private ImapClient $client, ...$args) {
                parent::__construct(...$args);
            }

            protected function openClient(array $account): ImapClient {
                return $this->client;
            }
        };

        $this->assertSame(0, $service->fetchNew());
        $this->assertSame(45, $store['imap_last_uid']);
        $this->assertSame(7, $store['imap_uid_validity']);
    }

    public function testStatsCountOpenDoneAutoAndWithoutStatus(): void {
        $make = static function (string $status, int $receivedAt, ?int $campaignId, ?int $inquiryId, ?string $note = null, ?string $doneBy = null): Reply {
            $r = new Reply();
            $r->setStatus($status);
            $r->setReceivedAt($receivedAt);
            $r->setCampaignId($campaignId);
            $r->setInquiryId($inquiryId);
            $r->setNote($note);
            $r->setDoneBy($doneBy);
            return $r;
        };
        $now = 1_800_000_000;
        $this->replies->method('findAll')->willReturn([
            $make(Reply::STATUS_OPEN, $now - 86400, 3, 5),
            $make(Reply::STATUS_OPEN, $now - 20 * 86400, null, null),
            $make(Reply::STATUS_DONE, $now - 2 * 86400, 3, 5, 'erledigt', 'kasse'),
            $make(Reply::STATUS_DONE, $now - 3600, 3, 5, Reply::AUTO_NOTE),
        ]);
        $this->inquiries->method('find')->willReturn($this->inquiry);

        $stats = $this->service->stats();

        $this->assertSame(2, $stats['open']);
        $this->assertSame($now - 20 * 86400, $stats['oldestOpenAt']);
        $this->assertSame(1, $stats['done']);
        $this->assertSame(1, $stats['auto']);
        $this->assertSame(2, $stats['lastWeek']);
        $this->assertSame(1, $stats['unassigned']);
        $this->assertSame(1, $stats['withoutStatus'], 'inquiry 5 has no status - counted once');
        $this->assertSame(4, $stats['total']);
        $this->assertCount(12, $stats['weeks']);
        $this->assertSame(2, $stats['weeks'][11]['count'], 'this week, auto-reply not counted');
        $this->assertSame(1, $stats['weeks'][9]['count'], '20 days ago');
    }
}
