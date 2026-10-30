<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\MailService;
use OCA\DonationReceipts\Service\ReceiptService;
use OCA\DonationReceipts\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class MailController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private MailService $mail,
        private ReceiptService $receipts,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function preview(int $id): JSONResponse {
        try {
            return new JSONResponse($this->mail->preview($this->receipts->find($id)));
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Bescheinigung nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    /**
     * A template edited in the dialog, rendered for the preview.
     */
    #[NoAdminRequired]
    public function render(int $id, string $subject, string $body = ''): JSONResponse {
        try {
            return new JSONResponse($this->mail->renderPreview($this->receipts->find($id), $subject, $body));
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Bescheinigung nicht gefunden'], Http::STATUS_NOT_FOUND);
        }
    }

    #[NoAdminRequired]
    #[UserRateLimit(limit: 60, period: 3600)]
    public function send(int $id, string $to, string $subject, string $body): JSONResponse {
        try {
            return new JSONResponse($this->mail->sendReceipt($this->receipts->find($id), $to, $subject, $body));
        } catch (DoesNotExistException) {
            return new JSONResponse(['error' => 'Bescheinigung nicht gefunden'], Http::STATUS_NOT_FOUND);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }

    #[NoAdminRequired]
    #[UserRateLimit(limit: 10, period: 600)]
    public function test(string $to, string $account = SettingsService::ACCOUNT_RECEIPTS): JSONResponse {
        if (!in_array($account, SettingsService::ACCOUNTS, true)) {
            return new JSONResponse(['error' => 'Unbekannter SMTP-Zugang'], Http::STATUS_BAD_REQUEST);
        }
        try {
            $this->mail->sendTest($to, $account);
            return new JSONResponse(['sent' => true]);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
    }
}
