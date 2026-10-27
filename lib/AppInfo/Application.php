<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\AppInfo;

use OCA\DonationReceipts\Listener\LoadNavigationEntryListener;
use OCA\DonationReceipts\Middleware\AccessMiddleware;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Navigation\Events\LoadAdditionalEntriesEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'donationreceipts';

    public function __construct() {
        parent::__construct(self::APP_ID);
    }

    public function register(IRegistrationContext $context): void {
        // Group check for every controller of this app (see AccessMiddleware).
        $context->registerMiddleware(AccessMiddleware::class);
        // Top-nav entry only for members of the allowed group.
        $context->registerEventListener(LoadAdditionalEntriesEvent::class, LoadNavigationEntryListener::class);
    }

    public function boot(IBootContext $context): void {
    }
}
