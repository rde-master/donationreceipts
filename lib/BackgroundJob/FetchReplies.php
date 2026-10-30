<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\BackgroundJob;

use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\ReplyService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Fetches replies to sponsor letters every 10 minutes, if switched on.
 */
class FetchReplies extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private ReplyService $replies,
        private LoggerInterface $logger,
    ) {
        parent::__construct($time);
        $this->setInterval(600);
    }

    protected function run($argument): void {
        if (!$this->replies->isEnabled()) {
            return;
        }
        try {
            $this->replies->fetchNew();
        } catch (ValidationException $e) {
            // kept for the settings page; logged once per run
            $this->logger->warning('Fetching sponsor replies failed: ' . $e->getMessage());
        }
    }
}
