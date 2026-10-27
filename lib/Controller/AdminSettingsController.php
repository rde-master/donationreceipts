<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Attribute\AccessGroupExempt;
use OCA\DonationReceipts\Service\AccessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IRequest;

/**
 * Admin-only (no #[NoAdminRequired]): choosing the group that may use the app.
 */
#[AccessGroupExempt]
class AdminSettingsController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private AccessService $accessService,
        private IGroupManager $groupManager,
    ) {
        parent::__construct($appName, $request);
    }

    public function setGroup(string $groupId): JSONResponse {
        if ($groupId !== '' && !$this->groupManager->groupExists($groupId)) {
            return new JSONResponse(['error' => 'Gruppe existiert nicht'], Http::STATUS_BAD_REQUEST);
        }
        $this->accessService->setAllowedGroup($groupId);
        return new JSONResponse(['groupId' => $groupId]);
    }
}
