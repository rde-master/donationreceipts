<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getKind()
 * @method void setKind(string $kind)
 * @method string|null getFirstName()
 * @method void setFirstName(?string $firstName)
 * @method string|null getLastName()
 * @method void setLastName(?string $lastName)
 * @method string|null getCompany()
 * @method void setCompany(?string $company)
 * @method string|null getStreet()
 * @method void setStreet(?string $street)
 * @method string|null getZip()
 * @method void setZip(?string $zip)
 * @method string|null getCity()
 * @method void setCity(?string $city)
 * @method string|null getCountry()
 * @method void setCountry(?string $country)
 * @method string|null getEmail()
 * @method void setEmail(?string $email)
 * @method int|null getFolderUnsignedId()
 * @method void setFolderUnsignedId(?int $id)
 * @method int|null getFolderSignedId()
 * @method void setFolderSignedId(?int $id)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $uid)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $timestamp)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $timestamp)
 */
class Donor extends Entity implements JsonSerializable {
    public const KIND_PERSON = 'person';
    public const KIND_COMPANY = 'company';

    // No defaults: Entity only persists values that differ from the initial one.
    protected $kind;
    protected $firstName;
    protected $lastName;
    protected $company;
    protected $street;
    protected $zip;
    protected $city;
    protected $country;
    protected $email;
    protected $folderUnsignedId;
    protected $folderSignedId;
    protected $notes;
    protected $createdBy;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('folderUnsignedId', 'integer');
        $this->addType('folderSignedId', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }

    /** "Vorname Nachname" or the company name. */
    public function getDisplayName(): string {
        if ($this->kind === self::KIND_COMPANY) {
            return (string)$this->company;
        }
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /**
     * Address block as it appears on the receipt.
     *
     * @return list<string>
     */
    public function getAddressLines(): array {
        $lines = [$this->getDisplayName()];
        if ((string)$this->street !== '') {
            $lines[] = (string)$this->street;
        }
        $city = trim($this->zip . ' ' . $this->city);
        if ($city !== '') {
            $lines[] = $city;
        }
        $country = trim((string)$this->country);
        if ($country !== '' && mb_strtolower($country) !== 'deutschland') {
            $lines[] = $country;
        }
        return $lines;
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'firstName' => $this->firstName ?? '',
            'lastName' => $this->lastName ?? '',
            'company' => $this->company ?? '',
            'displayName' => $this->getDisplayName(),
            'street' => $this->street ?? '',
            'zip' => $this->zip ?? '',
            'city' => $this->city ?? '',
            'country' => $this->country ?? '',
            'email' => $this->email ?? '',
            'notes' => $this->notes ?? '',
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
