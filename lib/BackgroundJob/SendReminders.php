<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\BackgroundJob;

use OCA\DonationReceipts\Service\InquiryService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use Psr\Log\LoggerInterface;

/**
 * Sends due reminders to sponsors once a day, if switched on
 * (Vorlagen → Erinnerungen automatisch senden).
 */
class SendReminders extends TimedJob {
    public function __construct(
        ITimeFactory $time,
        private InquiryService $inquiries,
        private LoggerInterface $logger,
    ) {
        parent::__construct($time);
        $this->setInterval(86400);
        $this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
    }

    protected function run($argument): void {
        try {
            $sent = $this->inquiries->sendDueReminders();
            if ($sent > 0) {
                $this->logger->info("Sent {$sent} reminders to sponsors");
            }
        } catch (\Throwable $e) {
            $this->logger->error('Sending reminders to sponsors failed', ['exception' => $e]);
        }
    }
}
