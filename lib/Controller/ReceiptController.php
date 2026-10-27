<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\ReceiptService;
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

class ReceiptController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private ReceiptService $receipts,
        private IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(?int $year = null, ?int $donorId = null): JSONResponse {
        $list = $this->receipts->list($year, $donorId);
        $donors = $this->receipts->donorsOf($list);
        return new JSONResponse([
            'receipts' => array_map(fn (Receipt $r) => $r->jsonSerialize() + [
                'donorName' => isset($donors[$r->getDonorId()]) ? $donors[$r->getDonorId()]->getDisplayName() : '',
            ], $list),
            'nextNumber' => $this->receipts->nextNumber(),
            'today' => $this->receipts->today(),
        ]);
    }

    #[NoAdminRequired]
    public function show(int $id): JSONResponse {
        try {
            return new JSONResponse($this->receipts->find($id));
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    /**
     * @param array<string, mixed> $receipt
     */
    #[NoAdminRequired]
    public function create(int $donorId, array $receipt): JSONResponse {
        try {
            $uid = $this->userSession->getUser()?->getUID() ?? '';
            return new JSONResponse($this->receipts->create($donorId, $receipt, $uid), Http::STATUS_CREATED);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Spender nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * @param array<string, mixed> $receipt
     */
    #[NoAdminRequired]
    public function update(int $id, array $receipt): JSONResponse {
        try {
            return new JSONResponse($this->receipts->update($id, $receipt));
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    /**
     * The PDF as generated from the current data (unsigned).
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function pdf(int $id): Response {
        try {
            $receipt = $this->receipts->find($id);
            return new DataDownloadResponse($this->receipts->renderPdf($receipt), $receipt->getNumber() . '.pdf', 'application/pdf');
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    private function notFound(): JSONResponse {
        return new JSONResponse(['error' => 'Bescheinigung nicht gefunden'], Http::STATUS_NOT_FOUND);
    }
}
