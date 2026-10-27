<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getNumber()
 * @method void setNumber(string $number)
 * @method int getDonorId()
 * @method void setDonorId(int $donorId)
 * @method string getType()
 * @method void setType(string $type)
 * @method int getAmountCents()
 * @method void setAmountCents(int $cents)
 * @method string getDonationDate()
 * @method void setDonationDate(string $isoDate)
 * @method string getIssueDate()
 * @method void setIssueDate(string $isoDate)
 * @method bool|null getExpenseWaiver()
 * @method void setExpenseWaiver(?bool $waiver)
 * @method string|null getGoodsDescription()
 * @method void setGoodsDescription(?string $description)
 * @method string|null getGoodsOrigin()
 * @method void setGoodsOrigin(?string $origin)
 * @method bool|null getGoodsValuationDocuments()
 * @method void setGoodsValuationDocuments(?bool $present)
 * @method int|null getFileUnsignedId()
 * @method void setFileUnsignedId(?int $id)
 * @method int|null getFileSignedId()
 * @method void setFileSignedId(?int $id)
 * @method int|null getSentAt()
 * @method void setSentAt(?int $timestamp)
 * @method string|null getSentTo()
 * @method void setSentTo(?string $email)
 * @method bool|null getChangedAfterSent()
 * @method void setChangedAfterSent(?bool $changed)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $uid)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $timestamp)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $timestamp)
 */
class Receipt extends Entity implements JsonSerializable {
    public const TYPE_MONEY = 'money';
    public const TYPE_GOODS = 'goods';

    public const ORIGIN_BUSINESS = 'business';
    public const ORIGIN_PRIVATE = 'private';
    public const ORIGIN_UNKNOWN = 'unknown';

    // No defaults: Entity only persists values that differ from the initial one.
    protected $number;
    protected $donorId;
    protected $type;
    protected $amountCents;
    protected $donationDate;
    protected $issueDate;
    protected $expenseWaiver;
    protected $goodsDescription;
    protected $goodsOrigin;
    protected $goodsValuationDocuments;
    protected $fileUnsignedId;
    protected $fileSignedId;
    protected $sentAt;
    protected $sentTo;
    protected $changedAfterSent;
    protected $createdBy;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('donorId', 'integer');
        $this->addType('amountCents', 'integer');
        $this->addType('expenseWaiver', 'boolean');
        $this->addType('goodsValuationDocuments', 'boolean');
        $this->addType('fileUnsignedId', 'integer');
        $this->addType('fileSignedId', 'integer');
        $this->addType('sentAt', 'integer');
        $this->addType('changedAfterSent', 'boolean');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }

    public function isSent(): bool {
        return $this->sentAt !== null;
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'donorId' => $this->donorId,
            'type' => $this->type,
            'amountCents' => $this->amountCents,
            'donationDate' => $this->donationDate,
            'issueDate' => $this->issueDate,
            'expenseWaiver' => (bool)$this->expenseWaiver,
            'goodsDescription' => $this->goodsDescription ?? '',
            'goodsOrigin' => $this->goodsOrigin ?? '',
            'goodsValuationDocuments' => (bool)$this->goodsValuationDocuments,
            'hasFiles' => $this->fileUnsignedId !== null && $this->fileSignedId !== null,
            'sentAt' => $this->sentAt,
            'sentTo' => $this->sentTo,
            'changedAfterSent' => (bool)$this->changedAfterSent,
            'createdBy' => $this->createdBy,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
