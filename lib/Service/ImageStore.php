<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\Exception\ValidationException;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Files\SimpleFS\ISimpleFolder;
use OCP\ITempManager;

/**
 * Signature and stamp images in the app data area - not reachable through
 * the Files app, so nobody can download or swap them there.
 */
class ImageStore {
    public const SIGNATURE = 'signature';
    public const STAMP = 'stamp';
    public const KINDS = [self::SIGNATURE, self::STAMP];

    private const FOLDER = 'images';
    private const MAX_BYTES = 2 * 1024 * 1024;
    private const MAX_PIXELS = 4000;

    public function __construct(
        private IAppData $appData,
        private ITempManager $tempManager,
    ) {
    }

    /**
     * @throws ValidationException not a usable PNG
     */
    public function put(string $kind, string $content): void {
        $this->assertKind($kind);
        if (strlen($content) > self::MAX_BYTES) {
            throw new ValidationException('Das Bild ist größer als 2 MB');
        }
        $info = @getimagesizefromstring($content);
        if ($info === false || $info[2] !== IMAGETYPE_PNG) {
            throw new ValidationException('Bitte eine PNG-Datei hochladen');
        }
        if ($info[0] > self::MAX_PIXELS || $info[1] > self::MAX_PIXELS) {
            throw new ValidationException('Das Bild ist größer als 4000 × 4000 Pixel');
        }
        $folder = $this->folder();
        if ($folder->fileExists($kind . '.png')) {
            $folder->getFile($kind . '.png')->putContent($content);
        } else {
            $folder->newFile($kind . '.png', $content);
        }
    }

    public function get(string $kind): ?string {
        $this->assertKind($kind);
        try {
            return $this->folder()->getFile($kind . '.png')->getContent();
        } catch (NotFoundException) {
            return null;
        }
    }

    public function exists(string $kind): bool {
        $this->assertKind($kind);
        return $this->folder()->fileExists($kind . '.png');
    }

    public function delete(string $kind): void {
        $this->assertKind($kind);
        try {
            $this->folder()->getFile($kind . '.png')->delete();
        } catch (NotFoundException) {
        }
    }

    /**
     * Local temp copy for the PDF library, which reads images from paths.
     */
    public function tempPath(string $kind): ?string {
        $content = $this->get($kind);
        if ($content === null) {
            return null;
        }
        $path = $this->tempManager->getTemporaryFile('.png');
        file_put_contents($path, $content);
        return $path;
    }

    private function folder(): ISimpleFolder {
        try {
            return $this->appData->getFolder(self::FOLDER);
        } catch (NotFoundException) {
            return $this->appData->newFolder(self::FOLDER);
        }
    }

    private function assertKind(string $kind): void {
        if (!in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException('Unknown image ' . $kind);
        }
    }
}
