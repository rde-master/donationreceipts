<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Db\Campaign;
use OCA\DonationReceipts\Db\Inquiry;
use OCA\DonationReceipts\Db\Sponsor;
use OCA\DonationReceipts\Mail\SmtpSender;
use OCA\DonationReceipts\Service\AccessService;
use OCA\DonationReceipts\Service\GroupNotifier;
use OCA\DonationReceipts\Service\SettingsService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

class GroupNotifierTest extends TestCase {
    private SettingsService&MockObject $settings;
    private SmtpSender&MockObject $sender;
    private INotificationManager&MockObject $notifications;
    private GroupNotifier $notifier;

    protected function setUp(): void {
        $users = [];
        foreach (['anna' => 'anna@verein.de', 'ben' => ''] as $uid => $email) {
            $user = $this->createMock(IUser::class);
            $user->method('getUID')->willReturn($uid);
            $user->method('getEMailAddress')->willReturn($email === '' ? null : $email);
            $users[] = $user;
        }
        $group = $this->createMock(IGroup::class);
        $group->method('getUsers')->willReturn($users);
        $groups = $this->createMock(IGroupManager::class);
        $groups->method('get')->with('vorstand')->willReturn($group);
        $access = $this->createMock(AccessService::class);
        $access->method('getAllowedGroup')->willReturn('vorstand');

        $this->notifications = $this->createMock(INotificationManager::class);
        $notification = $this->createMock(INotification::class);
        foreach (['setApp', 'setUser', 'setDateTime', 'setObject', 'setSubject'] as $setter) {
            $notification->method($setter)->willReturnSelf();
        }
        $this->notifications->method('createNotification')->willReturn($notification);

        $this->settings = $this->createMock(SettingsService::class);
        $this->sender = $this->createMock(SmtpSender::class);
        $this->sender->method('newEmail')->willReturn(new Email());
        $time = $this->createMock(ITimeFactory::class);
        $time->method('getTime')->willReturn(1_800_000_000);
        $this->notifier = new GroupNotifier($access, $groups, $this->notifications, $this->sender, $this->settings,
            $this->createMock(IURLGenerator::class), $time, $this->createMock(LoggerInterface::class));
    }

    private function respond(): void {
        $sponsor = new Sponsor();
        $sponsor->setName('Bäckerei Korn');
        $campaign = new Campaign();
        $campaign->setId(3);
        $campaign->setName('Sommerfest');
        $inquiry = new Inquiry();
        $inquiry->setId(5);
        $this->notifier->response($inquiry, $sponsor, $campaign, false);
    }

    public function testBellForEveryMemberAndMailToMembersWithAddress(): void {
        $this->settings->method('get')->with('notify_group_mail')->willReturn(true);
        $this->settings->method('missingForMail')->willReturn([]);
        $this->notifications->expects($this->exactly(2))->method('notify');
        $this->sender->expects($this->once())->method('sendEach')
            ->with(SettingsService::ACCOUNT_SPONSORS, $this->callback(static fn (array $emails) => array_keys($emails) === ['anna@verein.de']))
            ->willReturn(['anna@verein.de' => null]);

        $this->respond();
    }

    public function testNoMailWhenSwitchedOff(): void {
        $this->settings->method('get')->with('notify_group_mail')->willReturn(false);
        $this->notifications->expects($this->exactly(2))->method('notify');
        $this->sender->expects($this->never())->method('sendEach');

        $this->respond();
    }
}
