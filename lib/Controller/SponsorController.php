<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\SponsorService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class SponsorController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private SponsorService $sponsors,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(string $search = ''): JSONResponse {
        return new JSONResponse($this->sponsors->search($search));
    }

    #[NoAdminRequired]
    public function show(int $id): JSONResponse {
        try {
            return new JSONResponse($this->sponsors->find($id));
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    /**
     * @param array<string, mixed> $sponsor
     */
    #[NoAdminRequired]
    public function create(array $sponsor): JSONResponse {
        try {
            return new JSONResponse($this->sponsors->create($sponsor, $this->uid()), Http::STATUS_CREATED);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * @param array<string, mixed> $sponsor
     */
    #[NoAdminRequired]
    public function update(int $id, array $sponsor): JSONResponse {
        try {
            return new JSONResponse($this->sponsors->update($id, $sponsor));
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    #[NoAdminRequired]
    public function destroy(int $id): JSONResponse {
        try {
            $this->sponsors->delete($id);
            return new JSONResponse(['deleted' => $id]);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_CONFLICT);
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    /**
     * Existing donors (with e-mail) become sponsors.
     *
     * @param list<int> $donorIds
     */
    #[NoAdminRequired]
    public function import(array $donorIds): JSONResponse {
        return new JSONResponse($this->sponsors->importDonors(array_map('intval', $donorIds), $this->uid()));
    }

    private function uid(): string {
        return $this->userSession->getUser()?->getUID() ?? '';
    }

    private function notFound(): JSONResponse {
        return new JSONResponse(['error' => 'Möglicher Sponsor nicht gefunden'], Http::STATUS_NOT_FOUND);
    }
}
