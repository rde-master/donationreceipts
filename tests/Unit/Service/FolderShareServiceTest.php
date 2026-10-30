<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Tests\Unit\Service;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\FolderShareService;
use OCA\DonationReceipts\Service\StorageService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Constants;
use OCP\Files\Folder;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class FolderShareServiceTest extends TestCase {
    private IShareManager&MockObject $shares;
    private DonorMapper&MockObject $donors;
    private FolderShareService $service;
    private Donor $donor;

    protected function setUp(): void {
        $this->shares = $this->createMock(IShareManager::class);
        $this->donors = $this->createMock(DonorMapper::class);
        $folder = $this->createMock(Folder::class);
        $folder->method('getId')->willReturn(42);
        $storage = $this->createMock(StorageService::class);
        $storage->method('donorFolder')->with(StorageService::SIGNED)->willReturn($folder);
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('kasse');
        $session = $this->createMock(IUserSession::class);
        $session->method('getUser')->willReturn($user);
        $urls = $this->createMock(IURLGenerator::class);
        $urls->method('linkToRouteAbsolute')->willReturnCallback(
            static fn (string $route, array $params) => 'https://cloud.example.org/s/' . $params['token']
        );
        $time = $this->createMock(ITimeFactory::class);
        $time->method('getTime')->willReturn(1_800_000_000);
        $this->service = new FolderShareService($this->shares, $storage, $this->donors, $session, $urls, $time, $this->createMock(LoggerInterface::class));
        $this->donor = new Donor();
    }

    public function testExistingLinkIsReused(): void {
        $this->donor->setShareToken('oldtoken');
        $share = $this->share('oldtoken', 42);
        $this->shares->method('getShareByToken')->with('oldtoken')->willReturn($share);
        $this->shares->expects($this->never())->method('createShare');

        $this->assertSame('https://cloud.example.org/s/oldtoken', $this->service->link($this->donor));
    }

    public function testDeletedLinkIsCreatedAgainReadOnly(): void {
        $this->donor->setShareToken('gone');
        $this->shares->method('getShareByToken')->willThrowException(new ShareNotFound());
        $this->shares->method('shareApiAllowLinks')->willReturn(true);
        $this->shares->method('shareApiLinkEnforcePassword')->willReturn(false);
        $new = $this->createMock(IShare::class);
        foreach (['setNode', 'setShareType', 'setSharedBy', 'setLabel'] as $setter) {
            $new->method($setter)->willReturnSelf();
        }
        $new->expects($this->once())->method('setPermissions')->with(Constants::PERMISSION_READ)->willReturnSelf();
        $this->shares->method('newShare')->willReturn($new);
        $this->shares->expects($this->once())->method('createShare')->willReturn($this->share('newtoken', 42));
        $this->donors->expects($this->once())->method('update');

        $this->assertSame('https://cloud.example.org/s/newtoken', $this->service->link($this->donor));
        $this->assertSame('newtoken', $this->donor->getShareToken());
    }

    public function testEnforcedPasswordIsReported(): void {
        $this->shares->method('shareApiAllowLinks')->willReturn(true);
        $this->shares->method('shareApiLinkEnforcePassword')->willReturn(true);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Passwort');
        $this->service->link($this->donor);
    }

    private function share(string $token, int $nodeId): IShare&MockObject {
        $share = $this->createMock(IShare::class);
        $share->method('getToken')->willReturn($token);
        $share->method('getNodeId')->willReturn($nodeId);
        $share->method('getExpirationDate')->willReturn(null);
        return $share;
    }
}
