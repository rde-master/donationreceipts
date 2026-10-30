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
        'goodsDescription' => 2000, 'remarks' => 2000,
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
        return $this->inquiries->findOpenResponses();
    }

    /**
     * Key figures of a campaign's inquiries.
     *
     * @param list<Inquiry> $inquiries
     * @return array{sent: int, responded: int, received: int, pledgedCents: int, openCents: int, tickets: int, ticketsLater: int, publish: int}
     */
    public static function summary(array $inquiries): array {
        $summary = ['sent' => 0, 'responded' => 0, 'received' => 0, 'pledgedCents' => 0, 'openCents' => 0, 'tickets' => 0, 'ticketsLater' => 0, 'publish' => 0];
        foreach ($inquiries as $inquiry) {
            if ($inquiry->getSentAt() !== null) {
                $summary['sent']++;
            }
            if (!$inquiry->hasResponse()) {
                continue;
            }
            $summary['responded']++;
            $summary['pledgedCents'] += (int)$inquiry->getAmountCents();
            if ($inquiry->isReceived()) {
                $summary['received']++;
            } else {
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
        foreach ($sponsorIds as $sponsorId) {
            try {
                $sponsor = $this->sponsors->find($sponsorId);
            } catch (DoesNotExistException) {
                throw new ValidationException('Ein ausgewählter möglicher Sponsor existiert nicht mehr');
            }
            $inquiry = $this->inquiries->findByCampaignAndSponsor($campaignId, $sponsorId) ?? $this->createInquiry($campaign, $sponsor, $uid);
            $values = SponsorTemplate::values($sponsor, $campaign, $this->link($inquiry), $orgName);
            $emails[$sponsorId] = $this->withMessageId($this->sender->newEmail(SettingsService::ACCOUNT_SPONSORS, $sponsor->getEmail(),
                MailTemplate::render($subject, $values), MailHtml::render($body, $values)), $inquiry);
            $targets[$sponsorId] = [$sponsor, $inquiry];
        }

        $errors = $this->sender->sendEach(SettingsService::ACCOUNT_SPONSORS, $emails);

        $results = [];
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
        return [
            'org' => $this->settings->getString('org_name'),
            'campaign' => $campaign->getName(),
            'sponsor' => $sponsor->getName(),
            'intro' => MailTemplate::render($this->settings->getString('response_intro'), $values),
            'closed' => $campaign->isClosed(),
            'received' => $inquiry->isReceived(),
            'editable' => $this->isEditable($inquiry, $campaign),
            'respondedAt' => $inquiry->getRespondedAt(),
            'response' => $inquiry->hasResponse() ? $inquiry->responseData() : $this->prefill($sponsor),
            'logoUpload' => $this->logoUploadPossible($inquiry),
            'hasLogo' => $inquiry->getLogoFileId() !== null,
            'tickets' => SponsorTemplate::TICKET_LABELS,
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
        $logoFile = $logo !== null && ($data['publish'] ?? false) === true && $this->logoUploadPossible($inquiry)
            ? $this->checkLogo($logo)
            : null;

        $isUpdate = $inquiry->hasResponse();
        $this->applyResponse($inquiry, $data);
        $now = $this->time->getTime();
        $inquiry->setRespondedAt($now);
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
        $inquiry->setUpdatedAt($now);
        $inquiry = $this->inquiries->update($inquiry);
        if ($sendConfirmation) {
            $this->sendConfirmation($inquiry, $sponsor, $campaign);
        }
        return $inquiry;
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
            $to = trim((string)$donor->getEmail()) !== '' ? (string)$donor->getEmail() : $sponsor->getEmail();
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
        ];
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
                'email' => $sponsor->getEmail(),
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
            'email' => $current['email'] !== '' ? $current['email'] : $sponsor->getEmail(),
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
