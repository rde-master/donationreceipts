<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\ImageStore;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

/**
 * Upload and preview of the signature and stamp images.
 */
class ImageController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private ImageStore $images,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function show(string $kind): Response {
        $content = $this->images->get($kind);
        if ($content === null) {
            return new JSONResponse(['error' => 'Kein Bild hochgeladen'], Http::STATUS_NOT_FOUND);
        }
        $response = new DataDisplayResponse($content, Http::STATUS_OK, ['Content-Type' => 'image/png']);
        $response->cacheFor(0);
        return $response;
    }

    #[NoAdminRequired]
    public function upload(string $kind): JSONResponse {
        $file = $this->request->getUploadedFile('image');
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return new JSONResponse(['error' => 'Das Hochladen ist fehlgeschlagen'], Http::STATUS_BAD_REQUEST);
        }
        try {
            $this->images->put($kind, (string)file_get_contents($file['tmp_name']));
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
        return new JSONResponse(['kind' => $kind]);
    }

    #[NoAdminRequired]
    public function destroy(string $kind): JSONResponse {
        $this->images->delete($kind);
        return new JSONResponse(['kind' => $kind]);
    }
}
