<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class SettingsController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private SettingsService $settings,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function get(): JSONResponse {
        return new JSONResponse($this->serialize());
    }

    /**
     * @param array<string, mixed> $values
     */
    #[NoAdminRequired]
    public function update(array $values): JSONResponse {
        try {
            $this->settings->update($values);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
        return new JSONResponse($this->serialize());
    }

    private function serialize(): array {
        return [
            'values' => $this->settings->getAll(),
            'missing' => $this->settings->missingForReceipts(),
        ];
    }
}
