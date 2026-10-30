<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\AppInfo\Application;
use OCA\DonationReceipts\Db\CampaignMapper;
use OCA\DonationReceipts\Db\Inquiry;
use OCA\DonationReceipts\Db\InquiryMapper;
use OCA\DonationReceipts\Db\Reply;
use OCA\DonationReceipts\Db\ReplyMapper;
use OCA\DonationReceipts\Db\SponsorMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Mail\ImapClient;
use OCA\DonationReceipts\Mail\ImapException;
use OCA\DonationReceipts\Mail\MimeMessage;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception as DbException;
use OCP\IAppConfig;
use OCP\ICertificateManager;
use Psr\Log\LoggerInterface;

/**
 * Replies to sponsor letters from the mailbox of the sponsor account
 * (IMAP, read-only). Only new mails are looked at (UID after the last run);
 * a mail is kept if it answers one of our letters (Message-ID in
 * In-Reply-To/References) or comes from the address of a possible sponsor.
 * Every kept mail is a task ("open") until a group member marks it done.
 */
class ReplyService {
    /** Mails per run; the rest follows in the next run. */
    private const MAX_PER_RUN = 100;
    private const MAX_MESSAGE_BYTES = 2 * 1024 * 1024;
    private const MAX_BODY_CHARS = 50000;

    private const STATE_UID_VALIDITY = 'imap_uid_validity';
    private const STATE_LAST_UID = 'imap_last_uid';
    private const STATE_LAST_RUN = 'imap_last_run';
    private const STATE_LAST_ERROR = 'imap_last_error';

    public function __construct(
        private ReplyMapper $replies,
        private InquiryMapper $inquiries,
        private SponsorMapper $sponsors,
        private CampaignMapper $campaigns,
        private InquiryService $inquiryService,
        private GroupNotifier $groupNotifier,
        private SettingsService $settings,
        private IAppConfig $appConfig,
        private ICertificateManager $certificates,
        private ITimeFactory $time,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<Reply> newest first
     */
    public function list(?string $status = null, ?int $campaignId = null): array {
        return $this->replies->findAll($status, $campaignId);
    }

    public function countOpen(): int {
        return $this->replies->countOpen();
    }

    /**
     * Marks the task done (or open again), with an optional note.
     *
     * @throws ValidationException
     * @throws DoesNotExistException
     */
    public function setStatus(int $id, string $status, string $note, string $uid): Reply {
        if (!in_array($status, [Reply::STATUS_OPEN, Reply::STATUS_DONE], true)) {
            throw new ValidationException('Ungültiger Status');
        }
        $reply = $this->replies->find($id);
        $reply->setStatus($status);
        $reply->setNote(trim($note) === '' ? null : trim($note));
        $reply->setDoneBy($status === Reply::STATUS_DONE ? $uid : null);
        $reply->setDoneAt($status === Reply::STATUS_DONE ? $this->time->getTime() : null);
        return $this->replies->update($reply);
    }

    /**
     * Time and error of the last fetch, for the settings page.
     *
     * @return array{lastRun: int, lastError: string}
     */
    public function state(): array {
        return [
            'lastRun' => $this->appConfig->getValueInt(Application::APP_ID, self::STATE_LAST_RUN, 0),
            'lastError' => $this->appConfig->getValueString(Application::APP_ID, self::STATE_LAST_ERROR, ''),
        ];
    }

    /**
     * Connects and opens the folder, for "Verbindung testen".
     *
     * @return int number of messages in the folder
     * @throws ValidationException
     */
    public function test(): int {
        $this->assertConfigured();
        $account = $this->settings->imapAccount();
        try {
            $client = $this->openClient($account);
            $folder = $client->examine($account['folder']);
            $client->logout();
            return $folder['exists'];
        } catch (ImapException $e) {
            throw new ValidationException($e->getMessage());
        }
    }

    /**
     * Fetches new mails (background job every 10 minutes, or by hand).
     * On the very first run (or if the mailbox was rebuilt) nothing old
     * is imported - only mails arriving from then on.
     *
     * @return int number of new replies
     * @throws ValidationException not configured or IMAP failed
     */
    public function fetchNew(): int {
        $this->assertConfigured();
        $account = $this->settings->imapAccount();
        $this->appConfig->setValueInt(Application::APP_ID, self::STATE_LAST_RUN, $this->time->getTime());
        try {
            $client = $this->openClient($account);
            $folder = $client->examine($account['folder']);
            $validity = $this->appConfig->getValueInt(Application::APP_ID, self::STATE_UID_VALIDITY, 0);
            if ($validity !== $folder['uidValidity']) {
                $this->appConfig->setValueInt(Application::APP_ID, self::STATE_UID_VALIDITY, $folder['uidValidity']);
                $this->appConfig->setValueInt(Application::APP_ID, self::STATE_LAST_UID, max(0, $folder['uidNext'] - 1));
                $client->logout();
                $this->appConfig->setValueString(Application::APP_ID, self::STATE_LAST_ERROR, '');
                return 0;
            }
            $lastUid = $this->appConfig->getValueInt(Application::APP_ID, self::STATE_LAST_UID, 0);
            $new = 0;
            foreach (array_slice($client->uidsAfter($lastUid), 0, self::MAX_PER_RUN) as $uid) {
                $raw = $client->fetch($uid, self::MAX_MESSAGE_BYTES);
                if ($raw !== null && $this->import(MimeMessage::parse($raw), $raw) !== null) {
                    $new++;
                }
                $this->appConfig->setValueInt(Application::APP_ID, self::STATE_LAST_UID, $uid);
            }
            $client->logout();
            $this->appConfig->setValueString(Application::APP_ID, self::STATE_LAST_ERROR, '');
            return $new;
        } catch (ImapException $e) {
            $this->appConfig->setValueString(Application::APP_ID, self::STATE_LAST_ERROR, $e->getMessage());
            throw new ValidationException($e->getMessage());
        }
    }

    public function isEnabled(): bool {
        return (bool)$this->settings->get('sponsor_imap_enabled') && $this->settings->missingForImap() === [];
    }

    /**
     * Keeps a mail if it belongs to a letter or a possible sponsor.
     */
    public function import(MimeMessage $message, string $raw): ?Reply {
        $from = $message->from();
        if ($from['email'] === '' || $from['email'] === strtolower($this->settings->getString(SettingsService::ACCOUNT_SPONSORS . 'smtp_from_email'))) {
            return null;
        }
        $messageId = trim($message->rawHeader('Message-ID'));
        if ($messageId === '') {
            $messageId = '<sha1.' . sha1($raw) . '@donationreceipts>';
        }
        $messageId = mb_substr($messageId, 0, 255);
        if ($this->replies->existsByMessageId($messageId)) {
            return null;
        }

        $inquiry = $this->inquiryService->findByMessageReference($message->rawHeader('In-Reply-To') . ' ' . $message->rawHeader('References'));
        $sponsorId = $inquiry?->getSponsorId();
        if ($sponsorId === null) {
            $sponsor = $this->sponsors->findByEmail($from['email'])[0] ?? null;
            if ($sponsor === null) {
                return null; // neither a reply nor from a possible sponsor
            }
            $sponsorId = $sponsor->getId();
            $inquiry = $this->inquiries->findLatestBySponsor($sponsorId);
        }

        $auto = $message->isAutoReply();
        $now = $this->time->getTime();
        $reply = new Reply();
        $reply->setMessageId($messageId);
        $reply->setInquiryId($inquiry?->getId());
        $reply->setSponsorId($sponsorId);
        $reply->setCampaignId($inquiry?->getCampaignId());
        $reply->setFromEmail(mb_substr($from['email'], 0, 255));
        $reply->setFromName($from['name'] === '' ? null : mb_substr($from['name'], 0, 255));
        $reply->setSubject(mb_substr((string)$message->header('Subject'), 0, 255));
        $reply->setBody(mb_substr($message->text(), 0, self::MAX_BODY_CHARS));
        $reply->setReceivedAt($message->date($now));
        // vacation notices etc. are shown, but are no task
        $reply->setStatus($auto ? Reply::STATUS_DONE : Reply::STATUS_OPEN);
        $reply->setNote($auto ? 'Automatische Antwort (z. B. Abwesenheitsnotiz)' : null);
        $reply->setCreatedAt($now);
        try {
            $reply = $this->replies->insert($reply);
        } catch (DbException $e) {
            // fetched in parallel (background job and button) - already there
            if ($e->getReason() === DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
                return null;
            }
            throw $e;
        }

        if (!$auto) {
            $this->groupNotifier->reply($reply, $this->sponsorName($sponsorId), $this->campaignName($inquiry));
        }
        return $reply;
    }

    /**
     * @param array{host: string, port: int, ssl: bool, user: string, password: string, folder: string} $account
     * @throws ImapException
     */
    protected function openClient(array $account): ImapClient {
        $client = ImapClient::connect($account['host'], $account['port'], $account['ssl'], $this->certificates->getAbsoluteBundlePath());
        $client->login($account['user'], $account['password']);
        return $client;
    }

    /**
     * @throws ValidationException
     */
    private function assertConfigured(): void {
        $missing = $this->settings->missingForImap();
        if ($missing !== []) {
            throw new ValidationException('Für den Abruf der Antworten fehlt in den Einstellungen: ' . implode(', ', $missing));
        }
    }

    private function sponsorName(int $sponsorId): ?string {
        try {
            return $this->sponsors->find($sponsorId)->getName();
        } catch (DoesNotExistException) {
            return null;
        }
    }

    private function campaignName(?Inquiry $inquiry): ?string {
        if ($inquiry === null) {
            return null;
        }
        try {
            return $this->campaigns->find($inquiry->getCampaignId())->getName();
        } catch (DoesNotExistException) {
            return null;
        }
    }
}
