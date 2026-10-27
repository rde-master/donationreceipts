<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Settings;

use OCA\DonationReceipts\AppInfo\Application;
use OCA\DonationReceipts\Service\AccessService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\Settings\ISettings;
use OCP\Util;

/**
 * The only part of the app in the Nextcloud admin settings: which group may
 * use it. Everything else is configured inside the app itself.
 */
class AdminSettings implements ISettings {
    public function __construct(
        private AccessService $accessService,
        private IInitialState $initialState,
    ) {
    }

    public function getForm(): TemplateResponse {
        $this->initialState->provideInitialState('allowed-group', $this->accessService->getAllowedGroup());
        Util::addScript(Application::APP_ID, Application::APP_ID . '-admin');
        return new TemplateResponse(Application::APP_ID, 'admin');
    }

    public function getSection(): string {
        return Application::APP_ID;
    }

    public function getPriority(): int {
        return 10;
    }
}
