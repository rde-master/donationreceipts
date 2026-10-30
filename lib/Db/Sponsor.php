<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method string|null getContact()
 * @method void setContact(?string $contact)
 * @method string|null getSalutation()
 * @method void setSalutation(?string $salutation)
 * @method string getEmail()
 * @method void setEmail(string $email)
 * @method int|null getDonorId()
 * @method void setDonorId(?int $donorId)
 * @method string|null getNotes()
 * @method void setNotes(?string $notes)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $uid)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $timestamp)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $timestamp)
 */
class Sponsor extends Entity implements JsonSerializable {
    // No defaults: Entity only persists values that differ from the initial one.
    protected $name;
    protected $contact;
    protected $salutation;
    protected $email;
    protected $donorId;
    protected $notes;
    protected $createdBy;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('donorId', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact' => $this->contact ?? '',
            'salutation' => $this->salutation ?? '',
            'email' => $this->email,
            'donorId' => $this->donorId,
            'notes' => $this->notes ?? '',
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
