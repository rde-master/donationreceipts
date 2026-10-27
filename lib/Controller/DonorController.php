<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\DonorService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;

class DonorController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private DonorService $donors,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(string $search = ''): JSONResponse {
        return new JSONResponse($this->donors->search($search));
    }

    #[NoAdminRequired]
    public function show(int $id): JSONResponse {
        try {
            return new JSONResponse($this->donors->find($id));
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    /**
     * @param array<string, mixed> $donor
     */
    #[NoAdminRequired]
    public function create(array $donor): JSONResponse {
        try {
            $uid = $this->userSession->getUser()?->getUID() ?? '';
            return new JSONResponse($this->donors->create($donor, $uid), Http::STATUS_CREATED);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    /**
     * @param array<string, mixed> $donor
     */
    #[NoAdminRequired]
    public function update(int $id, array $donor): JSONResponse {
        try {
            return new JSONResponse($this->donors->update($id, $donor));
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    #[NoAdminRequired]
    public function destroy(int $id): JSONResponse {
        try {
            $this->donors->delete($id);
            return new JSONResponse(['deleted' => $id]);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_CONFLICT);
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    private function notFound(): JSONResponse {
        return new JSONResponse(['error' => 'Spender nicht gefunden'], Http::STATUS_NOT_FOUND);
    }
}
