<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\Exception\ValidationException;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\Security\ISecureRandom;

/**
 * Images inserted into mail templates (e.g. the club logo), in the app
 * data area. The editor shows them through the app's API; when a mail is
 * sent they are embedded (SmtpSender), so mail programs that block
 * external images still show them - also for mails sent without a logged
 * in user (confirmation after a sponsor response).
 */
class MailImageStore {
    private const FOLDER = 'mail-images';
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_PIXELS = 4000;
    private const TYPES = [IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_GIF => 'image/gif'];

    public function __construct(
        private IAppData $appData,
        private ISecureRandom $random,
    ) {
    }

    /**
     * @return string id of the stored image
     * @throws ValidationException not a usable image
     */
    public function put(string $content): string {
        if (strlen($content) > self::MAX_BYTES) {
            throw new ValidationException('Das Bild ist größer als 5 MB');
        }
        $info = @getimagesizefromstring($content);
        if ($info === false || !isset(self::TYPES[$info[2]])) {
            throw new ValidationException('Bitte ein Bild als PNG, JPG oder GIF hochladen');
        }
        if ($info[0] > self::MAX_PIXELS || $info[1] > self::MAX_PIXELS) {
            throw new ValidationException('Das Bild ist größer als 4000 × 4000 Pixel');
        }
        $id = $this->random->generate(32, '0123456789abcdef');
        $this->folder()->newFile($id, $content);
        return $id;
    }

    /**
     * @return array{content: string, mime: string}|null
     */
    public function get(string $id): ?array {
        if (preg_match('/^[a-f0-9]{32}$/', $id) !== 1) {
            return null;
        }
        try {
            $content = $this->folder()->getFile($id)->getContent();
        } catch (NotFoundException) {
            return null;
        }
        $info = @getimagesizefromstring($content);
        if ($info === false || !isset(self::TYPES[$info[2]])) {
            return null;
        }
        return ['content' => $content, 'mime' => self::TYPES[$info[2]]];
    }

    private function folder(): ISimpleFolder {
        try {
            return $this->appData->getFolder(self::FOLDER);
        } catch (NotFoundException) {
            return $this->appData->newFolder(self::FOLDER);
        }
    }
}
