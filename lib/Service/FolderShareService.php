<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Constants;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use Psr\Log\LoggerInterface;

/**
 * Public read-only link to the donor's folder of signed receipts, so the
 * donor can download them again at any time ({ordnerlink} in the receipt
 * mail). One link per donor: it is kept (token at the donor) and only
 * created again if it was deleted, expired or points to another folder.
 * The unsigned versions are never shared.
 */
class FolderShareService {
    public function __construct(
        private IShareManager $shareManager,
        private StorageService $storage,
        private DonorMapper $donors,
        private IUserSession $userSession,
        private IURLGenerator $urlGenerator,
        private ITimeFactory $time,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws ValidationException link shares not allowed or creating failed
     */
    public function link(Donor $donor): string {
        $folder = $this->storage->donorFolder(StorageService::SIGNED, $donor);

        $token = $donor->getShareToken();
        if ($token !== null) {
            try {
                $share = $this->shareManager->getShareByToken($token);
                $expires = $share->getExpirationDate();
                if ($share->getNodeId() === $folder->getId() && ($expires === null || $expires->getTimestamp() > $this->time->getTime())) {
                    return $this->url($share->getToken());
                }
            } catch (ShareNotFound) {
            }
        }

        $user = $this->userSession->getUser();
        if (!$this->shareManager->shareApiAllowLinks($user)) {
            throw new ValidationException('Öffentliche Links sind in dieser Nextcloud nicht erlaubt; der Ordnerlink kann nicht erzeugt werden');
        }
        if ($this->shareManager->shareApiLinkEnforcePassword()) {
            throw new ValidationException('Diese Nextcloud verlangt für öffentliche Links ein Passwort; der Ordnerlink kann nicht ohne Passwort erzeugt werden');
        }
        try {
            $share = $this->shareManager->newShare();
            $share->setNode($folder)
                ->setShareType(IShare::TYPE_LINK)
                ->setPermissions(Constants::PERMISSION_READ)
                ->setSharedBy((string)$user?->getUID())
                ->setLabel('Spendenbescheinigungen');
            $share = $this->shareManager->createShare($share);
        } catch (\Exception $e) {
            $this->logger->error('Sharing donor folder failed', ['exception' => $e]);
            throw new ValidationException('Der Ordnerlink konnte nicht erzeugt werden: ' . $e->getMessage());
        }

        $donor->setShareToken($share->getToken());
        $this->donors->update($donor);
        return $this->url($share->getToken());
    }

    private function url(string $token): string {
        return $this->urlGenerator->linkToRouteAbsolute('files_sharing.sharecontroller.showShare', ['token' => $token]);
    }
}
