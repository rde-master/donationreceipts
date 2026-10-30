<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Notification;

use OCA\DonationReceipts\AppInfo\Application;
use OCP\IURLGenerator;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * For the members of the allowed group (sent by GroupNotifier): a sponsor
 * responded via the form, or a reply mail arrived that needs handling.
 */
class Notifier implements INotifier {
    public const SUBJECT_RESPONSE = 'response';
    public const SUBJECT_REPLY = 'reply';

    public function __construct(
        private IURLGenerator $urlGenerator,
    ) {
    }

    public function getID(): string {
        return Application::APP_ID;
    }

    public function getName(): string {
        return 'Spendenbescheinigungen';
    }

    public function prepare(INotification $notification, string $languageCode): INotification {
        if ($notification->getApp() !== Application::APP_ID
            || !in_array($notification->getSubject(), [self::SUBJECT_RESPONSE, self::SUBJECT_REPLY], true)) {
            throw new UnknownNotificationException();
        }
        $params = $notification->getSubjectParameters();
        $sponsor = (string)($params['sponsor'] ?? '');
        $campaign = (string)($params['campaign'] ?? '');
        $base = $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.page.index');
        if ($notification->getSubject() === self::SUBJECT_REPLY) {
            $notification->setParsedSubject("Antwort-Mail von {$sponsor}" . ($campaign !== '' ? " zu „{$campaign}“" : '') . ' – bitte bearbeiten');
            $notification->setLink($base . '#/antworten');
        } else {
            $notification->setParsedSubject(!empty($params['update'])
                ? "{$sponsor} hat die Rückmeldung zu „{$campaign}“ geändert"
                : "{$sponsor} hat auf „{$campaign}“ geantwortet");
            $notification->setLink($base . '#/aktionen/' . (int)($params['campaignId'] ?? 0));
        }
        $notification->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg')));
        return $notification;
    }
}
