<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\ImageStore;
use OCA\DonationReceipts\Service\MailTemplate;
use OCA\DonationReceipts\Service\ReceiptService;
use OCA\DonationReceipts\Service\SettingsService;
use OCA\DonationReceipts\Service\SponsorTemplate;
use OCA\DonationReceipts\Service\StorageService;
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
        private StorageService $storage,
        private ImageStore $images,
        private ReceiptService $receipts,
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
            // Empty means "unchanged"; the stored passwords are never sent to the browser.
            $passwords = [];
            foreach (SettingsService::ACCOUNTS as $account) {
                $passwords[$account] = $values[$account . 'smtp_password'] ?? '';
                unset($values[$account . 'smtp_password']);
            }
            $imapPassword = $values['sponsor_imap_password'] ?? '';
            unset($values['sponsor_imap_password']);
            $this->settings->update($values);
            if (is_string($imapPassword) && $imapPassword !== '') {
                $this->settings->setImapPassword($imapPassword);
            }
            foreach ($passwords as $account => $password) {
                if (is_string($password) && $password !== '') {
                    $this->settings->setSmtpPassword($password, $account);
                }
            }
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
        return new JSONResponse($this->serialize());
    }

    #[NoAdminRequired]
    public function setFolder(string $variant, int $fileId): JSONResponse {
        try {
            $this->storage->setMainFolder($variant, $fileId);
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        }
        return new JSONResponse($this->serialize());
    }

    private function serialize(): array {
        return [
            'values' => $this->settings->getAll(),
            'missing' => $this->receipts->missingRequirements(),
            'folders' => $this->storage->mainFolderPaths(),
            'images' => [
                ImageStore::SIGNATURE => $this->images->exists(ImageStore::SIGNATURE),
                ImageStore::STAMP => $this->images->exists(ImageStore::STAMP),
            ],
            'nextNumber' => $this->receipts->nextNumber(),
            'smtpPasswordSet' => $this->settings->hasSmtpPassword(),
            'mailMissing' => $this->settings->missingForMail(),
            'placeholders' => MailTemplate::PLACEHOLDERS,
            'sponsorSmtpPasswordSet' => $this->settings->hasSmtpPassword(SettingsService::ACCOUNT_SPONSORS),
            'sponsorMailMissing' => $this->settings->missingForMail(SettingsService::ACCOUNT_SPONSORS),
            'imapPasswordSet' => $this->settings->hasImapPassword(),
            'sponsorPlaceholders' => SponsorTemplate::PLACEHOLDERS,
            'confirmPlaceholders' => SponsorTemplate::CONFIRM_PLACEHOLDERS,
        ];
    }
}
