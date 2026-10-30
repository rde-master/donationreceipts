<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\LetterTemplateService;
use OCA\DonationReceipts\Service\SponsorTemplate;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

/**
 * Templates for letters to sponsors.
 */
class LetterTemplateController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private LetterTemplateService $templates,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(): JSONResponse {
        return new JSONResponse([
            'templates' => $this->templates->list(),
            'placeholders' => SponsorTemplate::PLACEHOLDERS,
            'defaults' => ['subject' => SponsorTemplate::DEFAULT_LETTER_SUBJECT, 'body' => SponsorTemplate::DEFAULT_LETTER_BODY],
        ]);
    }

    #[NoAdminRequired]
    public function create(string $name, string $subject, string $body = ''): JSONResponse {
        try {
            return new JSONResponse($this->templates->create($name, $subject, $body), Http::STATUS_CREATED);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    #[NoAdminRequired]
    public function update(int $id, string $name, string $subject, string $body = ''): JSONResponse {
        try {
            return new JSONResponse($this->templates->update($id, $name, $subject, $body));
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    #[NoAdminRequired]
    public function destroy(int $id): JSONResponse {
        try {
            $this->templates->delete($id);
            return new JSONResponse(['deleted' => $id]);
        } catch (DoesNotExistException) {
            return $this->notFound();
        }
    }

    private function notFound(): JSONResponse {
        return new JSONResponse(['error' => 'Vorlage nicht gefunden'], Http::STATUS_NOT_FOUND);
    }
}
