<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;

/**
 * Decides who may use the app: exactly the members of the one group a
 * Nextcloud admin picks in the admin settings. As long as no group is
 * chosen, nobody gets in (not even admins - they only configure the group).
 */
class AccessService {
    public const CONFIG_KEY_GROUP = 'allowed_group';

    public function __construct(
        private IAppConfig $appConfig,
        private IGroupManager $groupManager,
    ) {
    }

    public function getAllowedGroup(): string {
        return $this->appConfig->getValueString(Application::APP_ID, self::CONFIG_KEY_GROUP, '');
    }

    public function setAllowedGroup(string $groupId): void {
        $this->appConfig->setValueString(Application::APP_ID, self::CONFIG_KEY_GROUP, $groupId);
    }

    public function isAllowed(?IUser $user): bool {
        if ($user === null) {
            return false;
        }
        $group = $this->getAllowedGroup();
        return $group !== '' && $this->groupManager->isInGroup($user->getUID(), $group);
    }
}
