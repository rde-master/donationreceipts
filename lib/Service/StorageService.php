<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Db\Receipt;
use OCA\DonationReceipts\Exception\ValidationException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IUserSession;

/**
 * Filing of the PDFs:
 *   <Hauptordner nicht unterschrieben>/<Spender>/<Nummer>_<Datum>.pdf
 *   <Hauptordner unterschrieben>/<Spender>/<Nummer>_<Datum>.pdf
 *
 * Folders and files are kept by their Nextcloud file ID, so renaming a donor
 * (or moving a folder within the team folder) keeps the assignment. All
 * access happens as the current user, who needs write access to both main
 * folders (e.g. a team folder of the allowed group).
 */
class StorageService {
    public const UNSIGNED = 'unsigned';
    public const SIGNED = 'signed';
    public const VARIANTS = [self::UNSIGNED, self::SIGNED];

    public function __construct(
        private IRootFolder $rootFolder,
        private IUserSession $userSession,
        private SettingsService $settings,
        private DonorMapper $donors,
    ) {
    }

    /**
     * Main folder of a variant, checked for existence and write access.
     *
     * @throws ValidationException
     */
    public function mainFolder(string $variant): Folder {
        $id = (int)$this->settings->get($this->settingKey($variant));
        $label = $variant === self::SIGNED ? 'unterschrieben' : 'nicht unterschrieben';
        if ($id === 0) {
            throw new ValidationException("Der Hauptordner „{$label}“ ist in den Einstellungen nicht gewählt");
        }
        $node = $this->userFolder()->getFirstNodeById($id);
        if (!$node instanceof Folder) {
            throw new ValidationException("Der Hauptordner „{$label}“ wurde nicht gefunden oder ist für dich nicht zugänglich");
        }
        if (!$node->isCreatable()) {
            throw new ValidationException("Im Hauptordner „{$label}“ fehlt dir das Schreibrecht");
        }
        return $node;
    }

    /**
     * Stores a folder picked in the settings as main folder of a variant.
     *
     * @return string the folder's path for display
     * @throws ValidationException
     */
    public function setMainFolder(string $variant, int $fileId): string {
        $node = $this->userFolder()->getFirstNodeById($fileId);
        if (!$node instanceof Folder) {
            throw new ValidationException('Der gewählte Ordner wurde nicht gefunden');
        }
        if (!$node->isCreatable()) {
            throw new ValidationException('Für den gewählten Ordner fehlt dir das Schreibrecht');
        }
        $this->settings->update([$this->settingKey($variant) => $fileId], true);
        return $this->relativePath($node);
    }

    /**
     * Paths of the main folders as the current user sees them ('' = not set or not accessible).
     *
     * @return array<string, string>
     */
    public function mainFolderPaths(): array {
        $paths = [];
        foreach (self::VARIANTS as $variant) {
            $id = (int)$this->settings->get($this->settingKey($variant));
            $node = $id !== 0 ? $this->userFolder()->getFirstNodeById($id) : null;
            $paths[$variant] = $node instanceof Folder ? $this->relativePath($node) : '';
        }
        return $paths;
    }

    /**
     * Writes (or replaces) the PDF of one variant and returns the file.
     * Replacing keeps the old content in Nextcloud's version history; a
     * changed issue date renames the file.
     *
     * @param list<File> $created receives files that did not exist before (for cleanup)
     * @throws ValidationException
     */
    public function store(string $variant, Donor $donor, Receipt $receipt, string $pdf, array &$created): File {
        $fileName = $receipt->getNumber() . '_' . $receipt->getIssueDate() . '.pdf';
        $existingId = $variant === self::SIGNED ? $receipt->getFileSignedId() : $receipt->getFileUnsignedId();

        if ($existingId !== null) {
            $existing = $this->userFolder()->getFirstNodeById($existingId);
            if ($existing instanceof File) {
                $parent = $existing->getParent();
                if ($existing->getName() !== $fileName) {
                    if ($parent->nodeExists($fileName)) {
                        throw new ValidationException("Die Datei „{$fileName}“ gibt es im Spenderordner schon");
                    }
                    $existing = $existing->move($parent->getPath() . '/' . $fileName);
                }
                $existing->putContent($pdf);
                return $existing;
            }
            // The file was deleted or moved out of reach: write a new one.
        }

        $folder = $this->donorFolder($variant, $donor);
        if ($folder->nodeExists($fileName)) {
            throw new ValidationException("Die Datei „{$fileName}“ gibt es im Spenderordner schon");
        }
        $file = $folder->newFile($fileName, $pdf);
        $created[] = $file;
        return $file;
    }

    /**
     * The donor's folder below the main folder; created if missing. An
     * existing folder of the same name is taken over unless it belongs to
     * another donor - then a number is appended ("Max Müller (2)").
     *
     * @throws ValidationException
     */
    public function donorFolder(string $variant, Donor $donor): Folder {
        $main = $this->mainFolder($variant);
        $signed = $variant === self::SIGNED;
        $assignedId = $signed ? $donor->getFolderSignedId() : $donor->getFolderUnsignedId();

        if ($assignedId !== null) {
            $node = $this->userFolder()->getFirstNodeById($assignedId);
            if ($node instanceof Folder) {
                return $node;
            }
        }

        $base = self::folderName($donor->getDisplayName());
        $folder = null;
        for ($i = 1; $folder === null; $i++) {
            $name = $i === 1 ? $base : "{$base} ({$i})";
            if (!$main->nodeExists($name)) {
                $folder = $main->newFolder($name);
            } else {
                $node = $main->get($name);
                if ($node instanceof Folder && !$this->belongsToOtherDonor($node->getId(), $donor, $signed)) {
                    $folder = $node;
                }
            }
        }

        if ($signed) {
            $donor->setFolderSignedId($folder->getId());
        } else {
            $donor->setFolderUnsignedId($folder->getId());
        }
        $this->donors->update($donor);
        return $folder;
    }

    public function getFile(?int $fileId): ?File {
        if ($fileId === null) {
            return null;
        }
        $node = $this->userFolder()->getFirstNodeById($fileId);
        return $node instanceof File ? $node : null;
    }

    /**
     * "Vorname Nachname" / company name with characters that are not
     * allowed in file names replaced.
     */
    public static function folderName(string $name): string {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\:*?"<>|]+/u', '-', $name) ?? '';
        $name = trim($name, " .\t");
        if (mb_strlen($name) > 120) {
            $name = rtrim(mb_substr($name, 0, 120));
        }
        return $name === '' ? 'Spender' : $name;
    }

    private function belongsToOtherDonor(int $folderId, Donor $donor, bool $signed): bool {
        $owner = $this->donors->findByFolderId($folderId, $signed);
        return $owner !== null && $owner->getId() !== $donor->getId();
    }

    private function settingKey(string $variant): string {
        return $variant === self::SIGNED ? 'folder_signed_id' : 'folder_unsigned_id';
    }

    private function relativePath(Folder $folder): string {
        return $this->userFolder()->getRelativePath($folder->getPath()) ?? $folder->getPath();
    }

    private function userFolder(): Folder {
        $uid = $this->userSession->getUser()?->getUID();
        if ($uid === null) {
            throw new \RuntimeException('No user');
        }
        return $this->rootFolder->getUserFolder($uid);
    }
}
