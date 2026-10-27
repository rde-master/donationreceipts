<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Service\AccessService;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

class AccessServiceTest extends TestCase {
    private function service(string $group, bool $inGroup): AccessService {
        $config = $this->createMock(IAppConfig::class);
        $config->method('getValueString')->willReturn($group);
        $groups = $this->createMock(IGroupManager::class);
        $groups->method('isInGroup')->willReturn($inGroup);
        return new AccessService($config, $groups);
    }

    private function user(): IUser {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('kasse');
        return $user;
    }

    public function testNoGroupConfiguredDeniesEveryone(): void {
        $this->assertFalse($this->service('', true)->isAllowed($this->user()));
    }

    public function testMemberIsAllowed(): void {
        $this->assertTrue($this->service('Spendenteam', true)->isAllowed($this->user()));
    }

    public function testNonMemberIsDenied(): void {
        $this->assertFalse($this->service('Spendenteam', false)->isAllowed($this->user()));
    }

    public function testGuestIsDenied(): void {
        $this->assertFalse($this->service('Spendenteam', true)->isAllowed(null));
    }
}
