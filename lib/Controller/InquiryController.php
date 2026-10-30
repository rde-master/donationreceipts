<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Db\Inquiry;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\CampaignService;
use OCA\DonationReceipts\Service\InquiryService;
use OCA\DonationReceipts\Service\SponsorService;
use OCA\DonationReceipts\Service\SponsorTemplate;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Sponsor responses inside the app: the prepared receipts waiting for
 * their donation, confirming the arrival, the uploaded logo.
 */
class InquiryController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private InquiryService $inquiries,
        private SponsorService $sponsors,
        private CampaignService $campaigns,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Responses whose donation has not arrived yet, with sponsor and campaign.
     */
    #[NoAdminRequired]
    public function open(): JSONResponse {
        $names = [];
        foreach ($this->campaigns->list() as $campaign) {
            $names[$campaign->getId()] = $campaign->getName();
        }
        $inquiries = array_map(function (Inquiry $inquiry) use ($names): array {
            try {
                $sponsor = $this->sponsors->find($inquiry->getSponsorId());
            } catch (DoesNotExistException) {
                $sponsor = null;
            }
            return $inquiry->jsonSerialize() + [
                'sponsor' => $sponsor,
                'campaignName' => $names[$inquiry->getCampaignId()] ?? '',
            ];
        }, $this->inquiries->openResponses());
        return new JSONResponse(['inquiries' => $inquiries, 'ticketLabels' => SponsorTemplate::TICKET_LABELS]);
    }

    /**
     * All responses (optionally of one campaign) for the list and the export.
     */
    #[NoAdminRequired]
    public function responses(?int $campaignId = null): JSONResponse {
        $names = [];
        foreach ($this->campaigns->list() as $campaign) {
            $names[$campaign->getId()] = $campaign->getName();
        }
        $sponsors = [];
        foreach ($this->sponsors->search() as $sponsor) {
            $sponsors[$sponsor->getId()] = $sponsor;
        }
        return new JSONResponse([
            'inquiries' => array_map(static fn (Inquiry $inquiry): array => $inquiry->jsonSerialize() + [
                'sponsor' => $sponsors[$inquiry->getSponsorId()] ?? null,
                'campaignName' => $names[$inquiry->getCampaignId()] ?? '',
            ], $this->inquiries->responses($campaignId)),
            'campaigns' => $this->campaigns->list(),
            'ticketLabels' => SponsorTemplate::TICKET_LABELS,
        ]);
    }

    /**
     * The donation has arrived: create the receipt and mail it.
     *
     * @param array<string, mixed> $receipt receipt fields plus donorId and updateDonor
     */
    #[NoAdminRequired]
    public function receive(int $id, array $receipt): JSONResponse {
        try {
            $uid = $this->userSession->getUser()?->getUID() ?? '';
            $result = $this->inquiries->receive($id, $receipt, $uid);
            return new JSONResponse([
                'inquiry' => $result['inquiry'],
                'receipt' => $result['receipt'],
                'mailError' => $result['mailError'],
            ]);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Rückmeldung oder Spender nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function logo(int $id): Response {
        try {
            $file = $this->inquiries->logo($id);
        } catch (DoesNotExistException) {
            $file = null;
        }
        if ($file === null) {
            return new JSONResponse(['error' => 'Das Logo wurde nicht gefunden oder ist für dich nicht zugänglich'], Http::STATUS_NOT_FOUND);
        }
        // Download, never inline: an SVG from outside must not run in the app's origin.
        return new DataDownloadResponse($file->getContent(), $file->getName(), 'application/octet-stream');
    }
}
