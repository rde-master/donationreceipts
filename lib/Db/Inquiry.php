<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Db;

use JsonSerializable;
use OCP\AppFramework\Db\Entity;

/**
 * A sponsor asked within a campaign, with the sponsor's response. A
 * response is the prepared receipt: once the donation has arrived, the
 * receipt is created from it (receiptId).
 *
 * @method int getCampaignId()
 * @method void setCampaignId(int $campaignId)
 * @method int getSponsorId()
 * @method void setSponsorId(int $sponsorId)
 * @method string getToken()
 * @method void setToken(string $token)
 * @method int|null getSentAt()
 * @method void setSentAt(?int $timestamp)
 * @method string|null getSentTo()
 * @method void setSentTo(?string $email)
 * @method string|null getSentBy()
 * @method void setSentBy(?string $uid)
 * @method int|null getRespondedAt()
 * @method void setRespondedAt(?int $timestamp)
 * @method string|null getKind()
 * @method void setKind(?string $kind)
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
 * @method string|null getType()
 * @method void setType(?string $type)
 * @method int|null getAmountCents()
 * @method void setAmountCents(?int $cents)
 * @method string|null getGoodsDescription()
 * @method void setGoodsDescription(?string $description)
 * @method string|null getGoodsOrigin()
 * @method void setGoodsOrigin(?string $origin)
 * @method string|null getTickets()
 * @method void setTickets(?string $tickets)
 * @method bool|null getPublish()
 * @method void setPublish(?bool $publish)
 * @method int|null getLogoFileId()
 * @method void setLogoFileId(?int $id)
 * @method string|null getRemarks()
 * @method void setRemarks(?string $remarks)
 * @method string|null getResponseBy()
 * @method void setResponseBy(?string $uid)
 * @method int|null getReceiptId()
 * @method void setReceiptId(?int $id)
 * @method string getCreatedBy()
 * @method void setCreatedBy(string $uid)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $timestamp)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $timestamp)
 */
class Inquiry extends Entity implements JsonSerializable {
    public const TICKETS_TWO = 'two';
    public const TICKETS_ONE = 'one';
    public const TICKETS_NONE = 'none';
    public const TICKETS_LATER = 'later';
    public const TICKETS = [self::TICKETS_TWO, self::TICKETS_ONE, self::TICKETS_NONE, self::TICKETS_LATER];

    // No defaults: Entity only persists values that differ from the initial one.
    protected $campaignId;
    protected $sponsorId;
    protected $token;
    protected $sentAt;
    protected $sentTo;
    protected $sentBy;
    protected $respondedAt;
    protected $kind;
    protected $firstName;
    protected $lastName;
    protected $company;
    protected $street;
    protected $zip;
    protected $city;
    protected $country;
    protected $type;
    protected $amountCents;
    protected $goodsDescription;
    protected $goodsOrigin;
    protected $tickets;
    protected $publish;
    protected $logoFileId;
    protected $remarks;
    protected $responseBy;
    protected $receiptId;
    protected $createdBy;
    protected $createdAt;
    protected $updatedAt;

    public function __construct() {
        $this->addType('campaignId', 'integer');
        $this->addType('sponsorId', 'integer');
        $this->addType('sentAt', 'integer');
        $this->addType('respondedAt', 'integer');
        $this->addType('amountCents', 'integer');
        $this->addType('publish', 'boolean');
        $this->addType('logoFileId', 'integer');
        $this->addType('receiptId', 'integer');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }

    public function hasResponse(): bool {
        return $this->respondedAt !== null;
    }

    /** The donation has arrived and the receipt exists. */
    public function isReceived(): bool {
        return $this->receiptId !== null;
    }

    /** "Vorname Nachname" or the company name from the response. */
    public function getDisplayName(): string {
        if ($this->kind === Donor::KIND_COMPANY) {
            return (string)$this->company;
        }
        return trim($this->firstName . ' ' . $this->lastName);
    }

    /**
     * Donor fields of the response, as DonorService expects them.
     *
     * @return array<string, string>
     */
    public function donorData(): array {
        return [
            'kind' => (string)$this->kind,
            'firstName' => (string)$this->firstName,
            'lastName' => (string)$this->lastName,
            'company' => (string)$this->company,
            'street' => (string)$this->street,
            'zip' => (string)$this->zip,
            'city' => (string)$this->city,
            'country' => (string)$this->country,
        ];
    }

    /**
     * The sponsor's answer (address, donation, extra fields).
     *
     * @return array<string, mixed>
     */
    public function responseData(): array {
        return $this->donorData() + [
            'type' => $this->type ?? '',
            'amountCents' => $this->amountCents,
            'goodsDescription' => $this->goodsDescription ?? '',
            'goodsOrigin' => $this->goodsOrigin ?? '',
            'tickets' => $this->tickets ?? '',
            'publish' => $this->hasResponse() ? (bool)$this->publish : null,
            'remarks' => $this->remarks ?? '',
        ];
    }

    public function jsonSerialize(): array {
        return [
            'id' => $this->id,
            'campaignId' => $this->campaignId,
            'sponsorId' => $this->sponsorId,
            'sentAt' => $this->sentAt,
            'sentTo' => $this->sentTo,
            'respondedAt' => $this->respondedAt,
            'response' => $this->hasResponse() ? $this->responseData() + ['displayName' => $this->getDisplayName()] : null,
            // who entered the response in the app (null = the sponsor via the form)
            'responseBy' => $this->responseBy,
            'hasLogo' => $this->logoFileId !== null,
            'receiptId' => $this->receiptId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
