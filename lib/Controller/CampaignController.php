<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Db\Campaign;
use OCA\DonationReceipts\Db\Inquiry;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\CampaignService;
use OCA\DonationReceipts\Service\InquiryService;
use OCA\DonationReceipts\Service\SettingsService;
use OCA\DonationReceipts\Service\SponsorService;
use OCA\DonationReceipts\Service\SponsorTemplate;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Campaigns, their sponsors with status and the letters to them.
 */
class CampaignController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private CampaignService $campaigns,
        private InquiryService $inquiries,
        private SponsorService $sponsors,
        private SettingsService $settings,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(): JSONResponse {
        return new JSONResponse(array_map(fn (Campaign $c) => $c->jsonSerialize() + [
            'summary' => InquiryService::summary($this->inquiries->forCampaign($c->getId())),
        ], $this->campaigns->list()));
    }

    #[NoAdminRequired]
    public function create(string $name): JSONResponse {
        try {
            return new JSONResponse($this->campaigns->create($name, $this->uid()), Http::STATUS_CREATED);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * The campaign with every sponsor and its inquiry (null = not written to yet).
     */
    #[NoAdminRequired]
    public function show(int $id): JSONResponse {
        try {
            $campaign = $this->campaigns->find($id);
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
        $inquiries = $this->inquiries->forCampaign($id);
        $bySponsor = [];
        foreach ($inquiries as $inquiry) {
            $bySponsor[$inquiry->getSponsorId()] = $inquiry;
        }
        $rows = [];
        foreach ($this->sponsors->search() as $sponsor) {
            $inquiry = $bySponsor[$sponsor->getId()] ?? null;
            $rows[] = ['sponsor' => $sponsor, 'inquiry' => $inquiry === null ? null : $this->serializeInquiry($inquiry)];
        }
        return new JSONResponse([
            'campaign' => $campaign,
            'summary' => InquiryService::summary($inquiries),
            'rows' => $rows,
            'ticketLabels' => SponsorTemplate::TICKET_LABELS,
            'mailMissing' => $this->settings->missingForMail(SettingsService::ACCOUNT_SPONSORS),
        ]);
    }

    #[NoAdminRequired]
    public function update(int $id, string $name, bool $closed = false): JSONResponse {
        try {
            return new JSONResponse($this->campaigns->update($id, $name, $closed));
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    #[NoAdminRequired]
    public function destroy(int $id): JSONResponse {
        try {
            $this->campaigns->delete($id);
            return new JSONResponse(['deleted' => $id]);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_CONFLICT);
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    #[NoAdminRequired]
    public function preview(int $id, int $sponsorId, string $subject, string $body = ''): JSONResponse {
        try {
            return new JSONResponse($this->inquiries->preview($id, $sponsorId, $subject, $body));
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Aktion oder Sponsor nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @param list<int> $sponsorIds
     */
    #[NoAdminRequired]
    #[UserRateLimit(limit: 30, period: 3600)]
    public function send(int $id, array $sponsorIds, string $subject, string $body = ''): JSONResponse {
        try {
            return new JSONResponse(['results' => $this->inquiries->sendLetters($id, $sponsorIds, $subject, $body, $this->uid())]);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    private function serializeInquiry(Inquiry $inquiry): array {
        return $inquiry->jsonSerialize() + ['link' => $this->inquiries->link($inquiry)];
    }

    private function uid(): string {
        return $this->userSession->getUser()?->getUID() ?? '';
    }

    private function notFound(): JSONResponse {
        return new JSONResponse(['error' => 'Aktion nicht gefunden'], Http::STATUS_NOT_FOUND);
    }
}
