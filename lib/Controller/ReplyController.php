<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Db\Reply;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\CampaignService;
use OCA\DonationReceipts\Service\ReplyService;
use OCA\DonationReceipts\Service\SponsorService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Reply mails from sponsors and their task status.
 */
class ReplyController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private ReplyService $replies,
        private SponsorService $sponsors,
        private CampaignService $campaigns,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(?string $status = null, ?int $campaignId = null): JSONResponse {
        $sponsorNames = [];
        foreach ($this->sponsors->search() as $sponsor) {
            $sponsorNames[$sponsor->getId()] = $sponsor->getName();
        }
        $campaignNames = [];
        foreach ($this->campaigns->list() as $campaign) {
            $campaignNames[$campaign->getId()] = $campaign->getName();
        }
        return new JSONResponse([
            'replies' => array_map(static fn (Reply $r) => $r->jsonSerialize() + [
                'sponsorName' => $sponsorNames[$r->getSponsorId()] ?? '',
                'campaignName' => $campaignNames[$r->getCampaignId()] ?? '',
            ], $this->replies->list($status !== '' ? $status : null, $campaignId)),
            'enabled' => $this->replies->isEnabled(),
        ] + $this->replies->state());
    }

    #[NoAdminRequired]
    public function count(): JSONResponse {
        return new JSONResponse(['open' => $this->replies->countOpen()]);
    }

    #[NoAdminRequired]
    public function update(int $id, string $status, string $note = ''): JSONResponse {
        try {
            $uid = $this->userSession->getUser()?->getUID() ?? '';
            return new JSONResponse($this->replies->setStatus($id, $status, $note, $uid));
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Antwort nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * "Jetzt abrufen" instead of waiting for the background job.
     */
    #[NoAdminRequired]
    #[UserRateLimit(limit: 20, period: 600)]
    public function fetch(): JSONResponse {
        try {
            return new JSONResponse(['new' => $this->replies->fetchNew()]);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    #[NoAdminRequired]
    #[UserRateLimit(limit: 10, period: 600)]
    public function test(): JSONResponse {
        try {
            return new JSONResponse(['messages' => $this->replies->test()]);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }
}
