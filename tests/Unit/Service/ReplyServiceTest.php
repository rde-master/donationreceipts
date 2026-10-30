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

    public function testAutoReplyIsNoTask(): void {
        $this->inquiryService->method('findByMessageReference')->willReturn($this->inquiry);
        $this->notifier->expects($this->never())->method('reply');

        $reply = $this->service->import($this->mail('anna@other.example', "Auto-Submitted: auto-replied\r\nReferences: <dr.5.abcdefgh.x@verein.de>\r\n"), 'raw');

        $this->assertSame(Reply::STATUS_DONE, $reply->getStatus());
    }

    public function testKnownMessageIsNotImportedTwice(): void {
        $this->replies->method('existsByMessageId')->willReturn(true);
        $this->replies->expects($this->never())->method('insert');
        $this->assertNull($this->service->import($this->mail('anna@other.example'), 'raw'));
    }
}
