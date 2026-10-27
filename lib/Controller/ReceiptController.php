<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\ReceiptService;
use OCA\DonationReceipts\Service\StorageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUserSession;

class ReceiptController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private ReceiptService $receipts,
        private StorageService $storage,
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
            return new JSONResponse($this->withFiles($this->receipts->find($id)));
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
            return new JSONResponse($this->withFiles($this->receipts->create($donorId, $receipt, $uid)), Http::STATUS_CREATED);
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
            return new JSONResponse($this->withFiles($this->receipts->update($id, $receipt)));
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    /**
     * One of the filed PDFs ('unsigned' or 'signed'), as stored in the donor's folder.
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function file(int $id, string $variant): Response {
        if (!in_array($variant, StorageService::VARIANTS, true)) {
            return $this->notFound();
        }
        try {
            $receipt = $this->receipts->find($id);
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
        $file = $this->storage->getFile($variant === StorageService::SIGNED ? $receipt->getFileSignedId() : $receipt->getFileUnsignedId());
        if ($file === null) {
            return new JSONResponse(['error' => 'Die PDF-Datei wurde nicht gefunden. Speichere die Bescheinigung erneut, um sie neu zu erzeugen.'], Http::STATUS_NOT_FOUND);
        }
        $response = new DataDisplayResponse($file->getContent(), Http::STATUS_OK, ['Content-Type' => 'application/pdf']);
        $response->addHeader('Content-Disposition', 'inline; filename="' . $file->getName() . '"');
        return $response;
    }

    /**
     * Receipt plus where its files are (as the current user sees them).
     */
    private function withFiles(Receipt $receipt): array {
        $files = [];
        foreach (StorageService::VARIANTS as $variant) {
            $file = $this->storage->getFile($variant === StorageService::SIGNED ? $receipt->getFileSignedId() : $receipt->getFileUnsignedId());
            $files[$variant] = $file === null ? null : ['id' => $file->getId(), 'name' => $file->getName()];
        }
        return $receipt->jsonSerialize() + ['files' => $files];
    }

    private function notFound(): JSONResponse {
        return new JSONResponse(['error' => 'Bescheinigung nicht gefunden'], Http::STATUS_NOT_FOUND);
    }
}
