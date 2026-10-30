<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * A mail from the mailbox of the sponsor account that answers a letter
 * (or comes from a possible sponsor), with its task status.
 *
 * @method string getMessageId()
 * @method void setMessageId(string $messageId)
 * @method int|null getInquiryId()
 * @method void setInquiryId(?int $id)
 * @method int|null getSponsorId()
 * @method void setSponsorId(?int $id)
 * @method int|null getCampaignId()
 * @method void setCampaignId(?int $id)
 * @method string getFromEmail()
 * @method void setFromEmail(string $email)
 * @method string|null getFromName()
 * @method void setFromName(?string $name)
 * @method string|null getSubject()
 * @method void setSubject(?string $subject)
 * @method string|null getBody()
 * @method void setBody(?string $body)
 * @method int getReceivedAt()
 * @method void setReceivedAt(int $timestamp)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getNote()
 * @method void setNote(?string $note)
 * @method string|null getDoneBy()
 * @method void setDoneBy(?string $uid)
 * @method int|null getDoneAt()
 * @method void setDoneAt(?int $timestamp)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $timestamp)
 */
class Reply extends Entity implements JsonSerializable {
    public const STATUS_OPEN = 'open';
    public const STATUS_DONE = 'done';

    // No defaults: Entity only persists values that differ from the initial one.
    protected $messageId;
    protected $inquiryId;
    protected $sponsorId;
    protected $campaignId;
    protected $fromEmail;
    protected $fromName;
    protected $subject;
    protected $body;
    protected $receivedAt;
    protected $status;
    protected $note;
    protected $doneBy;
    protected $doneAt;
    protected $createdAt;

    public function __construct() {
        $this->addType('inquiryId', 'integer');
        $this->addType('sponsorId', 'integer');
        $this->addType('campaignId', 'integer');
        $this->addType('receivedAt', 'integer');
        $this->addType('doneAt', 'integer');
        $this->addType('createdAt', 'integer');
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'inquiryId' => $this->inquiryId,
            'sponsorId' => $this->sponsorId,
            'campaignId' => $this->campaignId,
            'fromEmail' => $this->fromEmail,
            'fromName' => $this->fromName ?? '',
            'subject' => $this->subject ?? '',
            'body' => $this->body ?? '',
            'receivedAt' => $this->receivedAt,
            'status' => $this->status,
            'note' => $this->note ?? '',
            'doneBy' => $this->doneBy,
            'doneAt' => $this->doneAt,
        ];
    }
}
