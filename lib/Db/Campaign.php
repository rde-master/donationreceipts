<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getName()
 * @method void setName(string $name)
 * @method bool|null getClosed()
 * @method void setClosed(?bool $closed)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $uid)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $timestamp)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $timestamp)
 */
class Campaign extends Entity implements JsonSerializable {
    // No defaults: Entity only persists values that differ from the initial one.
    protected $name;
    protected $closed;
    protected $createdBy;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('closed', 'boolean');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }

    public function isClosed(): bool {
        return (bool)$this->closed;
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'closed' => $this->isClosed(),
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
