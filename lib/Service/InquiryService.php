<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\AppInfo\Application;
use OCA\DonationReceipts\Db\Campaign;
use OCA\DonationReceipts\Db\CampaignMapper;
use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Db\Inquiry;
use OCA\DonationReceipts\Db\InquiryMapper;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Db\Sponsor;
use OCA\DonationReceipts\Db\SponsorMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Mail\MailHtml;
use OCA\DonationReceipts\Mail\SmtpSender;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IURLGenerator;
use OCP\Mail\IMailer;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

/**
 * Letters to sponsors and their responses:
 *  1. sendLetters(): one inquiry per sponsor and campaign, each with its own
 *     secret link to the response form;
 *  2. respond(): the sponsor fills in the form (public, no login) - this is
 *     the prepared receipt; the group is notified, the sponsor gets a
 *     confirmation mail; changes are possible until step 3;
 *  3. receive(): a group member confirms that the donation has arrived -
 *     the donor is created or updated, the receipt is created and mailed.
 */
class InquiryService {
    /** Most recipients per sending (one request, one SMTP connection). */
    public const MAX_RECIPIENTS = 200;

    private const LOGO_MAX_BYTES = 10 * 1024 * 1024;
    private const LOGO_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/svg+xml' => 'svg',
        'application/pdf' => 'pdf',
    ];

    /** Response fields as on donors and receipts, with their maximum length. */
    private const TEXT_FIELDS = [
        'firstName' => 128, 'lastName' => 128, 'company' => 255,
        'street' => 255, 'zip' => 16, 'city' => 128, 'country' => 64,
        'goodsDescription' => 2000, 'remarks' => 2000, 'receiptEmail' => 255,
    ];

    /** Labels of the answer status, as in the app (outcome.js). */
    public const OUTCOME_LABELS = [
        Inquiry::OUTCOME_PLEDGED => 'Zusage',
        Inquiry::OUTCOME_INCOMPLETE => 'Zusage – Angaben fehlen noch',
        Inquiry::OUTCOME_LATER => 'Meldet sich noch',
        Inquiry::OUTCOME_DECLINED => 'Diesmal nicht',
        Inquiry::OUTCOME_NO_CONTACT => 'Bitte nicht mehr anschreiben',
    ];

    /** Highest amount accepted from the public form: 10 million euros. */
    private const MAX_AMOUNT_CENTS = 1_000_000_000;

    public function __construct(
        private InquiryMapper $inquiries,
        private CampaignMapper $campaigns,
        private SponsorMapper $sponsors,
        private DonorMapper $donorMapper,
        private SponsorService $sponsorService,
        private DonorService $donorService,
        private ReceiptService $receiptService,
        private MailService $mailService,
        private SettingsService $settings,
        private StorageService $storage,
        private SmtpSender $sender,
        private GroupNotifier $groupNotifier,
        private IURLGenerator $urlGenerator,
        private IMailer $mailer,
        private ISecureRandom $random,
        private ITimeFactory $time,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws DoesNotExistException
     */
    public function find(int $id): Inquiry {
        return $this->inquiries->find($id);
    }

    /**
     * @return list<Inquiry>
     */
    public function forCampaign(int $campaignId): array {
        return $this->inquiries->findByCampaign($campaignId);
    }

    /**
     * Responses whose donation has not arrived yet.
     *
     * @return list<Inquiry>
     */
    public function openResponses(): array {
        // a pledge withdrawn later ("diesmal nicht") is no open donation
        return array_values(array_filter($this->inquiries->findOpenResponses(), static fn (Inquiry $i): bool => $i->isPledged()));
    }

    /**
     * Key figures of a campaign's inquiries.
     *
     * @param list<Inquiry> $inquiries
     * "responded" counts every answer with a status; amounts, tickets and
     * logos only pledges with data.
     *
     * @return array{sent: int, responded: int, received: int, pledgedCents: int, openCents: int, tickets: int, ticketsLater: int, publish: int, incomplete: int, later: int, declined: int, repliesWithoutStatus: int}
     */
    public static function summary(array $inquiries): array {
        $summary = ['sent' => 0, 'responded' => 0, 'received' => 0, 'pledgedCents' => 0, 'openCents' => 0, 'tickets' => 0, 'ticketsLater' => 0, 'publish' => 0,
            'incomplete' => 0, 'later' => 0, 'declined' => 0, 'repliesWithoutStatus' => 0];
        foreach ($inquiries as $inquiry) {
            if ($inquiry->getSentAt() !== null) {
                $summary['sent']++;
            }
            $outcome = $inquiry->getEffectiveOutcome();
            if ($outcome === null) {
                if ($inquiry->getLastReplyAt() !== null) {
                    $summary['repliesWithoutStatus']++;
                }
                continue;
            }
            $summary['responded']++;
            if ($outcome === Inquiry::OUTCOME_INCOMPLETE) {
                $summary['incomplete']++;
            } elseif ($outcome === Inquiry::OUTCOME_LATER) {
                $summary['later']++;
            } elseif (in_array($outcome, Inquiry::OUTCOMES_REFUSED, true)) {
                $summary['declined']++;
            }
            if ($inquiry->isReceived()) {
                $summary['received']++;
            }
            if (!$inquiry->isPledged()) {
                continue;
            }
            $summary['pledgedCents'] += (int)$inquiry->getAmountCents();
            if (!$inquiry->isReceived()) {
                $summary['openCents'] += (int)$inquiry->getAmountCents();
            }
            $summary['tickets'] += match ($inquiry->getTickets()) {
                Inquiry::TICKETS_TWO => 2,
                Inquiry::TICKETS_ONE => 1,
                default => 0,
            };
            if ($inquiry->getTickets() === Inquiry::TICKETS_LATER) {
                $summary['ticketsLater']++;
            }
            if ($inquiry->getPublish()) {
                $summary['publish']++;
            }
        }
        return $summary;
    }

    public function link(Inquiry $inquiry): string {
        return $this->urlGenerator->linkToRouteAbsolute(Application::APP_ID . '.response.show', ['token' => $inquiry->getToken()]);
    }

    /**
     * The letter as one sponsor would get it. Before the first sending there
     * is no link yet; a placeholder text stands in for it.
     *
     * @return array{to: string, subject: string, html: string} html: complete document
     * @throws DoesNotExistException
     */
    public function preview(int $campaignId, int $sponsorId, string $subject, string $body): array {
        $campaign = $this->campaigns->find($campaignId);
        $sponsor = $this->sponsors->find($sponsorId);
        $inquiry = $this->inquiries->findByCampaignAndSponsor($campaignId, $sponsorId);
        $link = $inquiry !== null ? $this->link($inquiry) : '[persönlicher Link zum Rückmeldeformular]';
        $values = SponsorTemplate::values($sponsor, $campaign, $link, $this->settings->getString('org_name'));
        return [
            'to' => $sponsor->getEmail(),
            'subject' => MailTemplate::render($subject, $values),
            'html' => MailHtml::document(MailHtml::render(MailHtml::sanitize($body), $values)),
        ];
    }

    /**
     * Sends the letter (subject and text with placeholders) to each sponsor
     * with the personal link. Sponsors asked before keep their link.
     *
     * @param list<int> $sponsorIds
     * @return list<array{sponsorId: int, name: string, email: string, error: string|null}>
     * @throws ValidationException
     * @throws DoesNotExistException unknown campaign
     */
    public function sendLetters(int $campaignId, array $sponsorIds, string $subject, string $body, string $uid): array {
        $campaign = $this->campaigns->find($campaignId);
        if ($campaign->isClosed()) {
            throw new ValidationException('Die Aktion ist geschlossen');
        }
        if (trim($subject) === '') {
            throw new ValidationException('Der Betreff fehlt');
        }
        $sponsorIds = array_values(array_unique(array_map('intval', $sponsorIds)));
        if ($sponsorIds === []) {
            throw new ValidationException('Bitte mindestens einen möglichen Sponsor auswählen');
        }
        if (count($sponsorIds) > self::MAX_RECIPIENTS) {
            throw new ValidationException('Höchstens ' . self::MAX_RECIPIENTS . ' Empfänger auf einmal');
        }
        $missing = $this->settings->missingForMail(SettingsService::ACCOUNT_SPONSORS);
        if ($missing !== []) {
            throw new ValidationException('Bitte zuerst den SMTP-Zugang für Sponsoren-Anschreiben vervollständigen: ' . implode(', ', $missing));
        }

        $orgName = $this->settings->getString('org_name');
        $body = MailHtml::sanitize($body);
        $emails = [];
        $targets = [];
        // not sent at all: asked not to be written to / declined this campaign
        $skipped = [];
        foreach ($sponsorIds as $sponsorId) {
            try {
                $sponsor = $this->sponsors->find($sponsorId);
            } catch (DoesNotExistException) {
                throw new ValidationException('Ein ausgewählter möglicher Sponsor existiert nicht mehr');
            }
            $existing = $this->inquiries->findByCampaignAndSponsor($campaignId, $sponsorId);
            if ($sponsor->isNoContact()) {
                $skipped[] = ['sponsorId' => $sponsorId, 'name' => $sponsor->getName(), 'email' => $sponsor->getEmail(),
                    'error' => 'möchte nicht mehr angeschrieben werden – nicht gesendet'];
                continue;
            }
            if ($existing !== null && in_array($existing->getEffectiveOutcome(), Inquiry::OUTCOMES_REFUSED, true)) {
                $skipped[] = ['sponsorId' => $sponsorId, 'name' => $sponsor->getName(), 'email' => $sponsor->getEmail(),
                    'error' => 'hat für diese Aktion abgesagt – nicht gesendet'];
                continue;
            }
            $inquiry = $existing ?? $this->createInquiry($campaign, $sponsor, $uid);
            $values = SponsorTemplate::values($sponsor, $campaign, $this->link($inquiry), $orgName);
            $emails[$sponsorId] = $this->withMessageId($this->sender->newEmail(SettingsService::ACCOUNT_SPONSORS, $sponsor->getEmail(),
                MailTemplate::render($subject, $values), MailHtml::render($body, $values)), $inquiry);
            $targets[$sponsorId] = [$sponsor, $inquiry];
        }

        $errors = $emails === [] ? [] : $this->sender->sendEach(SettingsService::ACCOUNT_SPONSORS, $emails);

        $results = $skipped;
        $now = $this->time->getTime();
        foreach ($targets as $sponsorId => [$sponsor, $inquiry]) {
            $error = $errors[$sponsorId] ?? null;
            if ($error === null) {
                $inquiry->setSentAt($now);
                $inquiry->setSentTo($sponsor->getEmail());
                // The logo is filed in the sender's files; keep the first sender.
                if ($inquiry->getSentBy() === null) {
                    $inquiry->setSentBy($uid);
                }
                $inquiry->setUpdatedAt($now);
                $this->inquiries->update($inquiry);
            }
            $results[] = ['sponsorId' => $sponsorId, 'name' => $sponsor->getName(), 'email' => $sponsor->getEmail(), 'error' => $error];
        }
        return $results;
    }

    /**
     * What the public response form shows.
     *
     * @return array<string, mixed>
     * @throws DoesNotExistException unknown token
     */
    public function publicView(string $token): array {
        $inquiry = $this->inquiries->findByToken($token);
        $campaign = $this->campaigns->find($inquiry->getCampaignId());
        $sponsor = $this->sponsors->find($inquiry->getSponsorId());
        $values = SponsorTemplate::values($sponsor, $campaign, $this->link($inquiry), $this->settings->getString('org_name'));
        $response = $inquiry->hasResponse() ? $inquiry->responseData() : $this->prefill($sponsor);
        if ($response['receiptEmail'] === '') {
            $response['receiptEmail'] = $this->defaultReceiptEmail($sponsor);
        }
        return [
            'org' => $this->settings->getString('org_name'),
            'campaign' => $campaign->getName(),
            'sponsor' => $sponsor->getName(),
            'intro' => MailTemplate::render($this->settings->getString('response_intro'), $values),
            'closed' => $campaign->isClosed(),
            'received' => $inquiry->isReceived(),
            'editable' => $this->isEditable($inquiry, $campaign),
            'respondedAt' => $inquiry->getRespondedAt(),
            // status of the answer: "declined"/"no_contact" if the sponsor said no
            'outcome' => $inquiry->getEffectiveOutcome(),
            'outcomeNote' => $inquiry->getOutcomeBy() === null ? ($inquiry->getOutcomeNote() ?? '') : '',
            'response' => $response,
            'logoUpload' => $this->logoUploadPossible($inquiry),
            'hasLogo' => $inquiry->getLogoFileId() !== null,
            'tickets' => SponsorTemplate::TICKET_LABELS,
            'answers' => $this->answerTexts($values),
        ];
    }

    /**
     * Wording of the answer choices on the response form (settings, empty =
     * default), placeholders filled in.
     *
     * @param array<string, string> $values placeholder values
     * @return array{yes: array{label: string}, declined: array{label: string, hint: string}, no_contact: array{label: string, hint: string}}
     */
    private function answerTexts(array $values): array {
        $text = function (string $key) use ($values): string {
            $value = trim($this->settings->getString($key));
            return MailTemplate::render($value !== '' ? $value : SponsorTemplate::ANSWER_TEXTS[$key], $values);
        };
        return [
            'yes' => ['label' => $text('answer_yes_label')],
            'declined' => ['label' => $text('answer_declined_label'), 'hint' => $text('answer_declined_hint')],
            'no_contact' => ['label' => $text('answer_no_contact_label'), 'hint' => $text('answer_no_contact_hint')],
        ];
    }

    /**
     * Saves the sponsor's response (first time or changed), files the logo,
     * notifies the group and sends the sponsor a confirmation.
     *
     * @param array<string, mixed> $data
     * @param array{content: string, name: string}|null $logo uploaded file
     * @return array<string, mixed> the public view afterwards, plus a logo warning if filing failed
     * @throws ValidationException
     * @throws DoesNotExistException unknown token
     */
    public function respond(string $token, array $data, ?array $logo): array {
        $inquiry = $this->inquiries->findByToken($token);
        $campaign = $this->campaigns->find($inquiry->getCampaignId());
        $sponsor = $this->sponsors->find($inquiry->getSponsorId());
        if (!$this->isEditable($inquiry, $campaign)) {
            throw new ValidationException($inquiry->isReceived()
                ? 'Ihre Spende ist bereits eingegangen; die Angaben können nicht mehr geändert werden.'
                : 'Die Aktion ist beendet; Rückmeldungen sind nicht mehr möglich.');
        }
        $answer = $data['answer'] ?? 'yes';
        if ($answer !== 'yes') {
            return $this->decline($inquiry, $campaign, $sponsor, (string)$answer, $data['message'] ?? '', $token);
        }
        $logoFile = $logo !== null && ($data['publish'] ?? false) === true && $this->logoUploadPossible($inquiry)
            ? $this->checkLogo($logo)
            : null;

        $isUpdate = $inquiry->hasResponse();
        $this->applyResponse($inquiry, $data);
        $now = $this->time->getTime();
        $inquiry->setRespondedAt($now);
        $this->markPledged($inquiry, null, $now);
        $inquiry->setUpdatedAt($now);
        $inquiry = $this->inquiries->update($inquiry);

        $logoWarning = null;
        if ($logoFile !== null) {
            try {
                $file = $this->storage->storeLogo((string)$inquiry->getSentBy(), $inquiry->getLogoFileId(),
                    $campaign->getName(), $sponsor->getName(), $logoFile['extension'], $logoFile['content']);
                $inquiry->setLogoFileId($file->getId());
                $inquiry = $this->inquiries->update($inquiry);
            } catch (\Throwable $e) {
                $this->logger->error('Storing sponsor logo failed', ['exception' => $e]);
                $logoWarning = 'Ihre Angaben sind gespeichert, das Logo konnte aber nicht abgelegt werden. Bitte senden Sie es uns per E-Mail.';
            }
        }

        $this->groupNotifier->response($inquiry, $sponsor, $campaign, $isUpdate);
        $this->sendConfirmation($inquiry, $sponsor, $campaign);

        return $this->publicView($token) + ['logoWarning' => $logoWarning];
    }

    /**
     * A group member enters or corrects a response (e.g. after a phone
     * call) - also for sponsors that were never written to, and in closed
     * campaigns. Same checks as the form; no notification of the group.
     *
     * @param array<string, mixed> $data response fields as in the form
     * @throws ValidationException
     * @throws DoesNotExistException unknown campaign or sponsor
     */
    public function respondManually(int $campaignId, int $sponsorId, array $data, bool $sendConfirmation, string $uid): Inquiry {
        $campaign = $this->campaigns->find($campaignId);
        $sponsor = $this->sponsors->find($sponsorId);
        $inquiry = $this->inquiries->findByCampaignAndSponsor($campaignId, $sponsorId) ?? $this->createInquiry($campaign, $sponsor, $uid);
        if ($inquiry->isReceived()) {
            throw new ValidationException('Die Spende ist schon eingegangen; die Rückmeldung kann nicht mehr geändert werden');
        }
        $this->applyResponse($inquiry, $data);
        $now = $this->time->getTime();
        if (!$inquiry->hasResponse()) {
            $inquiry->setRespondedAt($now);
        }
        $inquiry->setResponseBy($uid);
        $this->markPledged($inquiry, $uid, $now);
        $inquiry->setUpdatedAt($now);
        $inquiry = $this->inquiries->update($inquiry);
        if ($sendConfirmation) {
            $this->sendConfirmation($inquiry, $sponsor, $campaign);
        }
        return $inquiry;
    }

    /**
     * Status of a sponsor's answer for a campaign without (or besides) the
     * data of a response - e.g. after a reply mail or a phone call.
     * "no_contact" is also kept on the sponsor; leaving it lifts that again.
     *
     * @throws ValidationException
     * @throws DoesNotExistException unknown campaign or sponsor
     */
    public function setOutcome(int $campaignId, int $sponsorId, string $outcome, string $note, string $uid): Inquiry {
        $campaign = $this->campaigns->find($campaignId);
        $sponsor = $this->sponsors->find($sponsorId);
        if (!in_array($outcome, Inquiry::OUTCOMES, true)) {
            throw new ValidationException('Ungültiger Status');
        }
        $note = trim($note);
        if (mb_strlen($note) > 2000) {
            throw new ValidationException('Die Notiz ist zu lang');
        }
        $inquiry = $this->inquiries->findByCampaignAndSponsor($campaignId, $sponsorId) ?? $this->createInquiry($campaign, $sponsor, $uid);
        if ($inquiry->isReceived()) {
            throw new ValidationException('Die Spende ist schon eingegangen; der Status kann nicht mehr geändert werden');
        }
        if ($outcome === Inquiry::OUTCOME_PLEDGED && !$inquiry->hasResponse()) {
            throw new ValidationException('Für eine Zusage bitte die Rückmeldung mit den Angaben erfassen (oder „Zusage – Angaben fehlen noch“ wählen)');
        }
        return $this->applyOutcome($inquiry, $sponsor, $outcome, $note, $uid);
    }

    /**
     * @param string|null $uid null = the sponsor via the form
     */
    private function applyOutcome(Inquiry $inquiry, Sponsor $sponsor, string $outcome, string $note, ?string $uid): Inquiry {
        $previous = $inquiry->getEffectiveOutcome();
        $now = $this->time->getTime();
        $inquiry->setOutcome($outcome);
        $inquiry->setOutcomeNote($note === '' ? null : $note);
        $inquiry->setOutcomeBy($uid);
        $inquiry->setOutcomeAt($now);
        $inquiry->setUpdatedAt($now);
        $inquiry = $this->inquiries->update($inquiry);

        if ($outcome === Inquiry::OUTCOME_NO_CONTACT) {
            $this->sponsorService->setNoContact($sponsor, true);
        } elseif ($previous === Inquiry::OUTCOME_NO_CONTACT) {
            $this->sponsorService->setNoContact($sponsor, false);
        }
        return $inquiry;
    }

    /**
     * The sponsor declines in the response form ("diesmal nicht" or "bitte
     * keine Anfragen mehr"), with an optional message. The group is told,
     * the sponsor gets a confirmation. Data entered before is kept, so a
     * change of mind only needs the form again.
     *
     * @return array<string, mixed> the public view afterwards
     * @throws ValidationException
     * @throws DoesNotExistException unknown token
     */
    private function decline(Inquiry $inquiry, Campaign $campaign, Sponsor $sponsor, string $answer, mixed $message, string $token): array {
        if (!in_array($answer, Inquiry::OUTCOMES_REFUSED, true)) {
            throw new ValidationException('Ungültige Antwort');
        }
        if (!is_string($message) || mb_strlen(trim($message)) > 2000) {
            throw new ValidationException('Die Nachricht ist zu lang');
        }
        $inquiry = $this->applyOutcome($inquiry, $sponsor, $answer, trim($message), null);
        $this->groupNotifier->declined($inquiry, $sponsor, $campaign);

        if ($this->settings->missingForMail(SettingsService::ACCOUNT_SPONSORS) === []) {
            $values = SponsorTemplate::values($sponsor, $campaign, $this->link($inquiry), $this->settings->getString('org_name'))
                + ['antwort' => SponsorTemplate::DECLINE_TEXTS[$answer]];
            try {
                $this->sender->send(SettingsService::ACCOUNT_SPONSORS, $this->withMessageId($this->sender->newEmail(
                    SettingsService::ACCOUNT_SPONSORS,
                    $sponsor->getEmail(),
                    MailTemplate::render($this->settings->getString('decline_subject'), $values),
                    MailHtml::render(MailHtml::sanitize($this->settings->getString('decline_body')), $values),
                ), $inquiry));
            } catch (ValidationException $e) {
                $this->logger->warning('Sending decline confirmation failed: ' . $e->getMessage());
            }
        }
        return $this->publicView($token) + ['logoWarning' => null];
    }

    /**
     * Can get a reminder: written to, no answer yet (or pledged without
     * data), nothing arrived, campaign open, sponsor not blocked. A reply
     * mail without status needs a person first.
     */
    public function isRemindable(Inquiry $inquiry, Sponsor $sponsor, Campaign $campaign): bool {
        if ($inquiry->getSentAt() === null || $inquiry->isReceived() || $campaign->isClosed() || $sponsor->isNoContact()) {
            return false;
        }
        $outcome = $inquiry->getEffectiveOutcome();
        if ($outcome === null) {
            return $inquiry->getLastReplyAt() === null;
        }
        return $outcome === Inquiry::OUTCOME_INCOMPLETE;
    }

    /**
     * Remindable, the last letter/reminder is "reminder_days" ago and fewer
     * than "reminder_max" reminders were sent.
     */
    public function isReminderDue(Inquiry $inquiry, Sponsor $sponsor, Campaign $campaign): bool {
        $last = $inquiry->getLastContactAt();
        return $this->isRemindable($inquiry, $sponsor, $campaign)
            && (int)$inquiry->getReminderCount() < (int)$this->settings->get('reminder_max')
            && $last !== null
            && $last <= $this->time->getTime() - (int)$this->settings->get('reminder_days') * 86400;
    }

    /**
     * Inquiries of a campaign with a due reminder.
     *
     * @return list<Inquiry>
     */
    public function dueReminders(int $campaignId): array {
        $campaign = $this->campaigns->find($campaignId);
        $due = [];
        foreach ($this->inquiries->findByCampaign($campaignId) as $inquiry) {
            try {
                $sponsor = $this->sponsors->find($inquiry->getSponsorId());
            } catch (DoesNotExistException) {
                continue;
            }
            if ($this->isReminderDue($inquiry, $sponsor, $campaign)) {
                $due[] = $inquiry;
            }
        }
        return $due;
    }

    /**
     * The reminder a sponsor would get (template by the status).
     *
     * @return array{to: string, subject: string, html: string}
     * @throws DoesNotExistException
     */
    public function previewReminder(int $campaignId, int $sponsorId): array {
        $campaign = $this->campaigns->find($campaignId);
        $sponsor = $this->sponsors->find($sponsorId);
        $inquiry = $this->inquiries->findByCampaignAndSponsor($campaignId, $sponsorId);
        if ($inquiry === null) {
            throw new DoesNotExistException('not written to');
        }
        [$subject, $html] = $this->reminderText($inquiry, $sponsor, $campaign);
        return ['to' => $sponsor->getEmail(), 'subject' => $subject, 'html' => $html];
    }

    /**
     * Sends the reminder (template by the status) to each remindable sponsor;
     * the others are reported as not sent.
     *
     * @param list<int> $sponsorIds
     * @return list<array{sponsorId: int, name: string, email: string, error: string|null}>
     * @throws ValidationException
     * @throws DoesNotExistException unknown campaign
     */
    public function sendReminders(int $campaignId, array $sponsorIds): array {
        $campaign = $this->campaigns->find($campaignId);
        if ($campaign->isClosed()) {
            throw new ValidationException('Die Aktion ist geschlossen');
        }
        $sponsorIds = array_values(array_unique(array_map('intval', $sponsorIds)));
        if ($sponsorIds === []) {
            throw new ValidationException('Bitte mindestens einen möglichen Sponsor auswählen');
        }
        if (count($sponsorIds) > self::MAX_RECIPIENTS) {
            throw new ValidationException('Höchstens ' . self::MAX_RECIPIENTS . ' Empfänger auf einmal');
        }
        $missing = $this->settings->missingForMail(SettingsService::ACCOUNT_SPONSORS);
        if ($missing !== []) {
            throw new ValidationException('Bitte zuerst den SMTP-Zugang für Sponsoren-Anschreiben vervollständigen: ' . implode(', ', $missing));
        }

        $results = [];
        $emails = [];
        $targets = [];
        foreach ($sponsorIds as $sponsorId) {
            try {
                $sponsor = $this->sponsors->find($sponsorId);
            } catch (DoesNotExistException) {
                continue;
            }
            $inquiry = $this->inquiries->findByCampaignAndSponsor($campaignId, $sponsorId);
            if ($inquiry === null || !$this->isRemindable($inquiry, $sponsor, $campaign)) {
                $results[] = ['sponsorId' => $sponsorId, 'name' => $sponsor->getName(), 'email' => $sponsor->getEmail(),
                    'error' => 'keine Erinnerung möglich (nicht angeschrieben, schon geantwortet oder abgesagt)'];
                continue;
            }
            [$subject, $html] = $this->reminderText($inquiry, $sponsor, $campaign);
            $emails[$sponsorId] = $this->withMessageId($this->sender->newEmail(SettingsService::ACCOUNT_SPONSORS, $sponsor->getEmail(), $subject, $html), $inquiry);
            $targets[$sponsorId] = [$sponsor, $inquiry];
        }

        $errors = $emails === [] ? [] : $this->sender->sendEach(SettingsService::ACCOUNT_SPONSORS, $emails);
        $now = $this->time->getTime();
        foreach ($targets as $sponsorId => [$sponsor, $inquiry]) {
            $error = $errors[$sponsorId] ?? null;
            if ($error === null) {
                $inquiry->setRemindedAt($now);
                $inquiry->setReminderCount((int)$inquiry->getReminderCount() + 1);
                $inquiry->setUpdatedAt($now);
                $this->inquiries->update($inquiry);
            }
            $results[] = ['sponsorId' => $sponsorId, 'name' => $sponsor->getName(), 'email' => $sponsor->getEmail(), 'error' => $error];
        }
        return $results;
    }

    /**
     * State of an inquiry as the lists show it: received, pledged,
     * incomplete, later, declined, no_contact, replied (mail without status),
     * waiting (written to) or failed (letter not sent).
     */
    public static function stateOf(Inquiry $inquiry): string {
        if ($inquiry->isReceived()) {
            return 'received';
        }
        $outcome = $inquiry->getEffectiveOutcome();
        if ($outcome !== null) {
            return $outcome;
        }
        if ($inquiry->getLastReplyAt() !== null) {
            return 'replied';
        }
        return $inquiry->getSentAt() !== null ? 'waiting' : 'failed';
    }

    /**
     * Per sponsor: the latest inquiry with its state, how often asked and
     * how many donations arrived (for the list of possible sponsors).
     *
     * @return array<int, array{last: array<string, mixed>, asked: int, donations: int, donatedCents: int}>
     */
    public function sponsorHistory(): array {
        $campaignNames = [];
        foreach ($this->campaigns->findAll() as $campaign) {
            $campaignNames[$campaign->getId()] = $campaign->getName();
        }
        $history = [];
        $latest = [];
        foreach ($this->inquiries->findAll() as $inquiry) {
            $sponsorId = $inquiry->getSponsorId();
            $entry = $history[$sponsorId] ?? ['asked' => 0, 'donations' => 0, 'donatedCents' => 0];
            if ($inquiry->getSentAt() !== null) {
                $entry['asked']++;
            }
            if ($inquiry->isReceived()) {
                $entry['donations']++;
                $entry['donatedCents'] += (int)$inquiry->getAmountCents();
            }
            $history[$sponsorId] = $entry;
            if (!isset($latest[$sponsorId])
                || [$inquiry->getCreatedAt(), $inquiry->getId()] > [$latest[$sponsorId]->getCreatedAt(), $latest[$sponsorId]->getId()]) {
                $latest[$sponsorId] = $inquiry;
            }
        }
        foreach ($latest as $sponsorId => $inquiry) {
            $times = array_filter([$inquiry->getSentAt(), $inquiry->getRemindedAt(), $inquiry->getLastReplyAt(),
                $inquiry->getOutcomeAt(), $inquiry->getRespondedAt()], static fn ($t) => $t !== null);
            $history[$sponsorId]['last'] = [
                'campaignId' => $inquiry->getCampaignId(),
                'campaignName' => $campaignNames[$inquiry->getCampaignId()] ?? '',
                'state' => self::stateOf($inquiry),
                'at' => $times === [] ? $inquiry->getCreatedAt() : max($times),
                'amountCents' => $inquiry->isPledged() ? $inquiry->getAmountCents() : null,
                'note' => (string)$inquiry->getOutcomeNote(),
            ];
        }
        return $history;
    }

    /**
     * Daily job: due reminders of all open campaigns, if switched on.
     *
     * @return int reminders sent
     */
    public function sendDueReminders(): int {
        if (!$this->settings->get('reminder_auto') || $this->settings->missingForMail(SettingsService::ACCOUNT_SPONSORS) !== []) {
            return 0;
        }
        $sent = 0;
        foreach ($this->campaigns->findAll() as $campaign) {
            if ($campaign->isClosed()) {
                continue;
            }
            $sponsorIds = array_map(static fn (Inquiry $i): int => $i->getSponsorId(), $this->dueReminders($campaign->getId()));
            foreach (array_chunk($sponsorIds, self::MAX_RECIPIENTS) as $chunk) {
                foreach ($this->sendReminders($campaign->getId(), $chunk) as $result) {
                    if ($result['error'] === null) {
                        $sent++;
                    } else {
                        $this->logger->warning("Reminder to {$result['email']} failed: {$result['error']}");
                    }
                }
            }
        }
        return $sent;
    }

    /**
     * @return array{0: string, 1: string} subject and HTML of the reminder
     */
    private function reminderText(Inquiry $inquiry, Sponsor $sponsor, Campaign $campaign): array {
        $prefix = $inquiry->getEffectiveOutcome() === Inquiry::OUTCOME_INCOMPLETE ? 'reminder_incomplete_' : 'reminder_';
        $values = SponsorTemplate::values($sponsor, $campaign, $this->link($inquiry), $this->settings->getString('org_name'));
        return [
            MailTemplate::render($this->settings->getString($prefix . 'subject'), $values),
            MailHtml::render(MailHtml::sanitize($this->settings->getString($prefix . 'body')), $values),
        ];
    }

    /**
     * A reply mail to the inquiry has arrived (shown as "Antwort per Mail"
     * until someone sets the status).
     */
    public function noteReply(Inquiry $inquiry, int $receivedAt): void {
        if ($inquiry->getLastReplyAt() !== null && $inquiry->getLastReplyAt() >= $receivedAt) {
            return;
        }
        $inquiry->setLastReplyAt($receivedAt);
        $this->inquiries->update($inquiry);
    }

    /**
     * A response with data is a pledge. Whoever sends one wants to donate,
     * so an earlier "nicht mehr anschreiben" is lifted on the sponsor.
     */
    private function markPledged(Inquiry $inquiry, ?string $uid, int $now): void {
        if ($inquiry->getOutcome() === Inquiry::OUTCOME_NO_CONTACT) {
            try {
                $this->sponsorService->setNoContact($this->sponsors->find($inquiry->getSponsorId()), false);
            } catch (DoesNotExistException) {
            }
        }
        $inquiry->setOutcome(Inquiry::OUTCOME_PLEDGED);
        $inquiry->setOutcomeNote(null);
        $inquiry->setOutcomeBy($uid);
        $inquiry->setOutcomeAt($now);
    }

    /**
     * All responses (optionally of one campaign) for the list and export.
     *
     * @return list<Inquiry>
     */
    public function responses(?int $campaignId = null): array {
        return $this->inquiries->findResponses($campaignId);
    }

    /**
     * Message-ID of mails about an inquiry: replies quote it in
     * In-Reply-To/References, so ReplyService can assign them.
     * "dr.<inquiry id>.<first 8 characters of the token>.<random>@<sender domain>"
     */
    public function withMessageId(Email $email, Inquiry $inquiry): Email {
        $from = $this->settings->getString(SettingsService::ACCOUNT_SPONSORS . 'smtp_from_email');
        $domain = str_contains($from, '@') ? substr($from, strrpos($from, '@') + 1) : 'localhost';
        $email->getHeaders()->addIdHeader('Message-ID', sprintf('dr.%d.%s.%s@%s',
            $inquiry->getId(), substr($inquiry->getToken(), 0, 8), $this->random->generate(12, ISecureRandom::CHAR_LOWER . ISecureRandom::CHAR_DIGITS), $domain));
        return $email;
    }

    /**
     * The inquiry a reply refers to, from its In-Reply-To/References.
     */
    public function findByMessageReference(string $references): ?Inquiry {
        if (preg_match_all('/<dr\.(\d+)\.([A-Za-z0-9]{8})\.[a-z0-9]+@[^>]+>/', $references, $matches, PREG_SET_ORDER) === 0) {
            return null;
        }
        foreach (array_reverse($matches) as [, $id, $tokenStart]) {
            try {
                $inquiry = $this->inquiries->find((int)$id);
            } catch (DoesNotExistException) {
                continue;
            }
            // the token part keeps a guessed id from matching
            if (hash_equals(substr($inquiry->getToken(), 0, 8), $tokenStart)) {
                return $inquiry;
            }
        }
        return null;
    }

    /**
     * The donation has arrived: creates or updates the donor, creates the
     * receipt from the response (with what the group member corrected) and
     * mails it. A failed mail does not undo the receipt; it is reported.
     *
     * @param array<string, mixed> $data receipt fields as for ReceiptService plus
     *                                   donorId (null = new donor) and updateDonor
     * @return array{inquiry: Inquiry, receipt: Receipt, mailError: string|null}
     * @throws ValidationException
     * @throws DoesNotExistException
     */
    public function receive(int $id, array $data, string $uid): array {
        $inquiry = $this->inquiries->find($id);
        if (!$inquiry->hasResponse()) {
            throw new ValidationException('Zu diesem Anschreiben gibt es noch keine Rückmeldung');
        }
        if ($inquiry->isReceived()) {
            throw new ValidationException('Für diese Rückmeldung gibt es schon eine Bescheinigung');
        }
        $sponsor = $this->sponsors->find($inquiry->getSponsorId());
        // Fails before the donor is touched if the receipt can't be created.
        $this->receiptService->validate($data);

        $donor = $this->donorFor($inquiry, $sponsor, $data, $uid);
        $this->sponsorService->linkDonor($sponsor, $donor->getId());

        $receipt = $this->receiptService->create($donor->getId(), $data, $uid);
        $inquiry->setReceiptId($receipt->getId());
        $inquiry->setUpdatedAt($this->time->getTime());
        $inquiry = $this->inquiries->update($inquiry);

        $mailError = null;
        try {
            $to = $this->receiptRecipient($inquiry, $donor, $sponsor);
            $template = $this->mailService->templates();
            $receipt = $this->mailService->sendReceipt($receipt, $to, $template['subject'], $template['body']);
        } catch (ValidationException $e) {
            $mailError = $e->getMessage();
        }
        return ['inquiry' => $inquiry, 'receipt' => $receipt, 'mailError' => $mailError];
    }

    /**
     * The logo of a response as the current user sees it.
     *
     * @throws DoesNotExistException
     */
    public function logo(int $id): ?\OCP\Files\File {
        return $this->storage->getFile($this->inquiries->find($id)->getLogoFileId());
    }

    private function createInquiry(Campaign $campaign, Sponsor $sponsor, string $uid): Inquiry {
        $inquiry = new Inquiry();
        $inquiry->setCampaignId($campaign->getId());
        $inquiry->setSponsorId($sponsor->getId());
        $inquiry->setToken($this->random->generate(32, ISecureRandom::CHAR_ALPHANUMERIC));
        $now = $this->time->getTime();
        $inquiry->setCreatedBy($uid);
        $inquiry->setCreatedAt($now);
        $inquiry->setUpdatedAt($now);
        return $this->inquiries->insert($inquiry);
    }

    private function isEditable(Inquiry $inquiry, Campaign $campaign): bool {
        return !$campaign->isClosed() && !$inquiry->isReceived();
    }

    private function logoUploadPossible(Inquiry $inquiry): bool {
        return $inquiry->getSentBy() !== null && (int)$this->settings->get('folder_logos_id') !== 0;
    }

    /**
     * Address for a first response: from the linked donor, otherwise the
     * sponsor's name as company.
     *
     * @return array<string, mixed>
     */
    private function prefill(Sponsor $sponsor): array {
        $donor = null;
        if ($sponsor->getDonorId() !== null) {
            try {
                $donor = $this->donorMapper->find($sponsor->getDonorId());
            } catch (DoesNotExistException) {
            }
        }
        $address = $donor !== null
            ? array_intersect_key($donor->jsonSerialize(), array_flip(['kind', 'firstName', 'lastName', 'company', 'street', 'zip', 'city', 'country']))
            : ['kind' => Donor::KIND_COMPANY, 'firstName' => '', 'lastName' => '', 'company' => $sponsor->getName(), 'street' => '', 'zip' => '', 'city' => '', 'country' => ''];
        return $address + [
            'type' => Receipt::TYPE_MONEY,
            'amountCents' => null,
            'goodsDescription' => '',
            'goodsOrigin' => '',
            'tickets' => '',
            'publish' => null,
            'remarks' => '',
            'receiptEmail' => $this->defaultReceiptEmail($sponsor, $donor),
        ];
    }

    /**
     * Prefill of "Spendenbescheinigung senden an": the linked donor's
     * address, otherwise the sponsor's.
     */
    private function defaultReceiptEmail(Sponsor $sponsor, ?Donor $donor = null): string {
        if ($donor === null && $sponsor->getDonorId() !== null) {
            try {
                $donor = $this->donorMapper->find($sponsor->getDonorId());
            } catch (DoesNotExistException) {
            }
        }
        $email = $donor !== null ? trim((string)$donor->getEmail()) : '';
        return $email !== '' ? $email : $sponsor->getEmail();
    }

    /**
     * Where the receipt goes: the address from the response, otherwise the
     * donor's, otherwise the sponsor's.
     */
    private function receiptRecipient(Inquiry $inquiry, Donor $donor, Sponsor $sponsor): string {
        foreach ([$inquiry->getReceiptEmail(), $donor->getEmail()] as $email) {
            if (trim((string)$email) !== '') {
                return trim((string)$email);
            }
        }
        return $sponsor->getEmail();
    }

    /**
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    private function applyResponse(Inquiry $inquiry, array $data): void {
        $text = [];
        foreach (self::TEXT_FIELDS as $field => $max) {
            $value = $data[$field] ?? '';
            if (!is_string($value)) {
                throw new ValidationException('Ungültige Eingabe');
            }
            $value = trim($value);
            if (mb_strlen($value) > $max) {
                throw new ValidationException('Eine Eingabe ist zu lang');
            }
            $text[$field] = $value === '' ? null : $value;
        }

        $kind = $data['kind'] ?? null;
        if ($kind === Donor::KIND_PERSON) {
            if ($text['firstName'] === null || $text['lastName'] === null) {
                throw new ValidationException('Bitte Vor- und Nachnamen angeben');
            }
            $text['company'] = null;
        } elseif ($kind === Donor::KIND_COMPANY) {
            if ($text['company'] === null) {
                throw new ValidationException('Bitte den Namen der Firma angeben');
            }
            $text['firstName'] = null;
            $text['lastName'] = null;
        } else {
            throw new ValidationException('Bitte Firma oder Privatperson wählen');
        }
        if ($text['street'] === null || $text['zip'] === null || $text['city'] === null) {
            throw new ValidationException('Bitte die vollständige Anschrift angeben – sie steht auf der Zuwendungsbestätigung');
        }

        if ($text['receiptEmail'] !== null && !$this->mailer->validateMailAddress($text['receiptEmail'])) {
            throw new ValidationException('Bitte eine gültige E-Mail-Adresse für die Zuwendungsbestätigung angeben');
        }

        $type = $data['type'] ?? null;
        if (!in_array($type, [Receipt::TYPE_MONEY, Receipt::TYPE_GOODS], true)) {
            throw new ValidationException('Bitte Geld- oder Sachspende wählen');
        }
        $amount = $data['amountCents'] ?? null;
        if (!is_int($amount) || $amount <= 0 || $amount > self::MAX_AMOUNT_CENTS) {
            throw new ValidationException($type === Receipt::TYPE_GOODS ? 'Bitte den Wert der Sachspende angeben' : 'Bitte den Betrag angeben');
        }
        $origin = null;
        if ($type === Receipt::TYPE_GOODS) {
            if ($text['goodsDescription'] === null) {
                throw new ValidationException('Bitte die Sachspende beschreiben');
            }
            $origin = $data['goodsOrigin'] ?? null;
            if (!in_array($origin, [Receipt::ORIGIN_BUSINESS, Receipt::ORIGIN_PRIVATE, Receipt::ORIGIN_UNKNOWN], true)) {
                throw new ValidationException('Bitte angeben, ob die Sachspende aus dem Betriebs- oder Privatvermögen stammt');
            }
        } else {
            $text['goodsDescription'] = null;
        }

        $tickets = $data['tickets'] ?? null;
        if (!in_array($tickets, Inquiry::TICKETS, true)) {
            throw new ValidationException('Bitte angeben, ob Freikarten gewünscht sind');
        }
        $publish = $data['publish'] ?? null;
        if (!is_bool($publish)) {
            throw new ValidationException('Bitte angeben, ob wir Ihre Unterstützung öffentlich bekannt geben dürfen');
        }

        $inquiry->setKind($kind);
        foreach ($text as $field => $value) {
            $inquiry->{'set' . ucfirst($field)}($value);
        }
        $inquiry->setType($type);
        $inquiry->setAmountCents($amount);
        $inquiry->setGoodsOrigin($origin);
        $inquiry->setTickets($tickets);
        $inquiry->setPublish($publish);
    }

    /**
     * @param array{content: string, name: string} $logo
     * @return array{content: string, extension: string}
     * @throws ValidationException
     */
    private function checkLogo(array $logo): array {
        if (strlen($logo['content']) > self::LOGO_MAX_BYTES) {
            throw new ValidationException('Das Logo ist größer als 10 MB');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($logo['content']);
        // finfo reports SVG without XML declaration as text/html or text/plain
        if (in_array($mime, ['text/xml', 'application/xml', 'text/plain', 'text/html'], true)
            && preg_match('/<svg[\s>]/i', substr($logo['content'], 0, 4096)) === 1) {
            $mime = 'image/svg+xml';
        }
        $extension = self::LOGO_TYPES[$mime] ?? null;
        if ($extension === null) {
            throw new ValidationException('Bitte das Logo als PNG, JPG, SVG oder PDF hochladen');
        }
        return ['content' => $logo['content'], 'extension' => $extension];
    }

    /**
     * The donor for the receipt: the chosen one (optionally updated with the
     * address from the response) or a new one from the response.
     *
     * @param array<string, mixed> $data
     * @throws ValidationException
     * @throws DoesNotExistException
     */
    private function donorFor(Inquiry $inquiry, Sponsor $sponsor, array $data, string $uid): Donor {
        $donorId = $data['donorId'] ?? null;
        if ($donorId === null) {
            return $this->donorService->create($inquiry->donorData() + [
                'email' => $inquiry->getReceiptEmail() ?? $sponsor->getEmail(),
                'notes' => '',
            ], $uid);
        }
        if (!is_int($donorId)) {
            throw new ValidationException('Ungültiger Spender');
        }
        $donor = $this->donorMapper->find($donorId);
        if (($data['updateDonor'] ?? false) !== true) {
            return $donor;
        }
        $current = $donor->jsonSerialize();
        return $this->donorService->update($donorId, $inquiry->donorData() + [
            'email' => $inquiry->getReceiptEmail() ?? ($current['email'] !== '' ? $current['email'] : $sponsor->getEmail()),
            'notes' => $current['notes'],
        ]);
    }

    /**
     * Confirmation to the sponsor's address. Failing (or no SMTP account)
     * does not affect the saved response.
     */
    private function sendConfirmation(Inquiry $inquiry, Sponsor $sponsor, Campaign $campaign): void {
        if ($this->settings->missingForMail(SettingsService::ACCOUNT_SPONSORS) !== []) {
            return;
        }
        $values = SponsorTemplate::values($sponsor, $campaign, $this->link($inquiry), $this->settings->getString('org_name'))
            + ['angaben' => SponsorTemplate::summary($inquiry)];
        try {
            $this->sender->send(SettingsService::ACCOUNT_SPONSORS, $this->withMessageId($this->sender->newEmail(
                SettingsService::ACCOUNT_SPONSORS,
                $sponsor->getEmail(),
                MailTemplate::render($this->settings->getString('confirm_subject'), $values),
                MailHtml::render(MailHtml::sanitize($this->settings->getString('confirm_body')), $values),
            ), $inquiry));
        } catch (ValidationException $e) {
            $this->logger->warning('Sending sponsor confirmation failed: ' . $e->getMessage());
        }
    }
}
