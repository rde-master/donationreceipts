<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Db;

use JsonSerializable;
use OCA\DonationReceipts\Mail\MailHtml;
use OCP\AppFramework\Db\Entity;

/**
 * Template of a letter to sponsors (subject and HTML text with placeholders).
 *
 * @method string getName()
 * @method void setName(string $name)
 * @method string getSubject()
 * @method void setSubject(string $subject)
 * @method string|null getBody()
 * @method void setBody(?string $body)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $timestamp)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $timestamp)
 */
class LetterTemplate extends Entity implements JsonSerializable {
    // No defaults: Entity only persists values that differ from the initial one.
    protected $name;
    protected $subject;
    protected $body;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'subject' => $this->subject,
            'body' => MailHtml::fromStored($this->body ?? ''),
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
