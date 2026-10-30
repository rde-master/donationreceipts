<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\AppInfo\Application;
use OCA\DonationReceipts\Db\Campaign;
use OCA\DonationReceipts\Db\Inquiry;
use OCA\DonationReceipts\Db\Reply;
use OCA\DonationReceipts\Db\Sponsor;
use OCA\DonationReceipts\Mail\SmtpSender;
use OCA\DonationReceipts\Notification\Notifier;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;

/**
 * Tells the members of the allowed group about a sponsor response or a
 * reply mail: Nextcloud notification (bell) and, if enabled, a short mail
 * to their e-mail address. Failures are logged, never thrown - the
 * response itself is saved already.
 */
class GroupNotifier {
    public function __construct(
        private AccessService $access,
        private IGroupManager $groupManager,
        private INotificationManager $notifications,
        private SmtpSender $sender,
        private SettingsService $settings,
        private IURLGenerator $urlGenerator,
        private ITimeFactory $time,
        private LoggerInterface $logger,
    ) {
    }

    public function response(Inquiry $inquiry, Sponsor $sponsor, Campaign $campaign, bool $isUpdate): void {
        $this->notify('inquiry', (int)$inquiry->getId(), Notifier::SUBJECT_RESPONSE, [
            'sponsor' => $sponsor->getName(),
            'campaign' => $campaign->getName(),
            'campaignId' => $campaign->getId(),
            'update' => $isUpdate,
        ]);
        $subject = $isUpdate
            ? "Rückmeldung geändert: {$sponsor->getName()} ({$campaign->getName()})"
            : "Neue Rückmeldung: {$sponsor->getName()} ({$campaign->getName()})";
        $this->mail($subject, '<p>' . $this->escape($subject) . '</p>'
            . '<p>' . nl2br($this->escape(SponsorTemplate::summary($inquiry)), false) . '</p>'
            . $this->appLink('aktionen/' . $campaign->getId(), 'In der App öffnen'));
    }

    public function reply(Reply $reply, ?string $sponsorName, ?string $campaignName): void {
        $from = $sponsorName ?? $reply->getFromEmail();
        $this->notify('reply', (int)$reply->getId(), Notifier::SUBJECT_REPLY, [
            'sponsor' => $from,
            'campaign' => (string)$campaignName,
        ]);
        $subject = "Antwort von {$from}" . ($campaignName !== null ? " ({$campaignName})" : '') . ' – zu bearbeiten';
        $excerpt = mb_substr(trim((string)$reply->getBody()), 0, 1500);
        $this->mail($subject, '<p>' . $this->escape($subject) . '</p>'
            . '<p><strong>' . $this->escape((string)$reply->getSubject()) . '</strong></p>'
            . '<p>' . nl2br($this->escape($excerpt), false) . '</p>'
            . $this->appLink('antworten', 'Antwort in der App bearbeiten'));
    }

    /**
     * @param array<string, mixed> $params
     */
    private function notify(string $objectType, int $objectId, string $subject, array $params): void {
        try {
            // An earlier notification about the same object is replaced.
            $previous = $this->notifications->createNotification();
            $previous->setApp(Application::APP_ID)->setObject($objectType, (string)$objectId);
            $this->notifications->markProcessed($previous);

            foreach ($this->members() as $user) {
                $notification = $this->notifications->createNotification();
                $notification->setApp(Application::APP_ID)
                    ->setUser($user->getUID())
                    ->setDateTime(new \DateTime('@' . $this->time->getTime()))
                    ->setObject($objectType, (string)$objectId)
                    ->setSubject($subject, $params);
                $this->notifications->notify($notification);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Notifying the group failed', ['exception' => $e]);
        }
    }

    /**
     * Info mail to every member with an e-mail address, over the sponsor
     * account (or the receipt account if that one isn't set up).
     */
    private function mail(string $subject, string $html): void {
        if (!$this->settings->get('notify_group_mail')) {
            return;
        }
        $account = $this->settings->missingForMail(SettingsService::ACCOUNT_SPONSORS) === []
            ? SettingsService::ACCOUNT_SPONSORS
            : SettingsService::ACCOUNT_RECEIPTS;
        if ($this->settings->missingForMail($account) !== []) {
            return;
        }
        try {
            $emails = [];
            foreach ($this->members() as $user) {
                $address = trim((string)$user->getEMailAddress());
                if ($address !== '') {
                    $emails[$address] = $this->sender->newEmail($account, $address, $subject, $html);
                }
            }
            foreach ($this->sender->sendEach($account, $emails) as $address => $error) {
                if ($error !== null) {
                    $this->logger->warning("Info mail to {$address} failed: {$error}");
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Info mail to the group failed', ['exception' => $e]);
        }
    }

    /**
     * @return list<IUser>
     */
    private function members(): array {
        $group = $this->groupManager->get($this->access->getAllowedGroup());
        return $group === null ? [] : array_values($group->getUsers());
    }

    private function appLink(string $route, string $caption): string {
        $url = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index') . '#/' . $route;
        return '<p><a href="' . $this->escape($url) . '">' . $this->escape($caption) . '</a></p>';
    }

    private function escape(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
