<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Listener;

use OCA\DonationReceipts\AppInfo\Application;
use OCA\DonationReceipts\Service\AccessService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

/**
 * Adds the top-nav entry only for members of the allowed group. Done on
 * LoadAdditionalEntriesEvent rather than in Application::boot() because the
 * request's user isn't authenticated yet when apps are booted.
 *
 * @template-implements IEventListener<LoadAdditionalEntriesEvent>
 */
class LoadNavigationEntryListener implements IEventListener {
    public function __construct(
        private IUserSession $userSession,
        private AccessService $accessService,
        private INavigationManager $navigationManager,
        private IURLGenerator $urlGenerator,
    ) {
    }

    public function handle(Event $event): void {
        if (!$event instanceof LoadAdditionalEntriesEvent) {
            return;
        }
        if (!$this->accessService->isAllowed($this->userSession->getUser())) {
            return;
        }

        $urlGenerator = $this->urlGenerator;
        $this->navigationManager->add(static function () use ($urlGenerator): array {
            return [
                'id' => Application::APP_ID,
                'order' => 60,
                'href' => $urlGenerator->linkToRoute(Application::APP_ID . '.page.index'),
                'icon' => $urlGenerator->imagePath(Application::APP_ID, 'app.svg'),
                'name' => 'Spendenbescheinigungen',
                'type' => INavigationManager::TYPE_APPS,
            ];
        });
    }
}
