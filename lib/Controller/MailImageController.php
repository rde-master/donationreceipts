<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\MailImageStore;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

/**
 * Images in mail templates: upload from the editor and display there.
 */
class MailImageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private MailImageStore $images,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[UserRateLimit(limit: 60, period: 3600)]
    public function upload(): JSONResponse {
        $file = $this->request->getUploadedFile('image');
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return new JSONResponse(['error' => 'Das Hochladen ist fehlgeschlagen'], Http::STATUS_BAD_REQUEST);
        }
        try {
            return new JSONResponse(['id' => $this->images->put((string)file_get_contents($file['tmp_name']))], Http::STATUS_CREATED);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function show(string $id): Response {
        $image = $this->images->get($id);
        if ($image === null) {
            return new JSONResponse(['error' => 'Bild nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
        $response = new DataDisplayResponse($image['content'], Http::STATUS_OK, ['Content-Type' => $image['mime']]);
        // ids are random and never reused
        $response->cacheFor(7 * 24 * 3600);
        return $response;
    }
}
