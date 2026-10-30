<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Db\InquiryMapper;
use OCA\DonationReceipts\Db\Sponsor;
use OCA\DonationReceipts\Db\SponsorMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Mail\IMailer;

/**
 * The list of sponsors to write to: name, contact person, e-mail. A sponsor
 * can be linked to a donor; receipts for its donations go to that donor.
 */
class SponsorService {
    private const FIELDS = ['name', 'contact', 'salutation', 'email', 'notes'];

    public function __construct(
        private SponsorMapper $mapper,
        private DonorMapper $donors,
        private InquiryMapper $inquiries,
        private IMailer $mailer,
        private ITimeFactory $time,
    ) {
    }

    /**
     * @return list<Sponsor> sorted by name
     */
    public function search(string $term = ''): array {
        $sponsors = $this->mapper->search($term);
        $collator = class_exists(\Collator::class) ? new \Collator('de_DE') : null;
        usort($sponsors, static fn (Sponsor $a, Sponsor $b): int => $collator !== null
            ? $collator->compare($a->getName(), $b->getName())
            : strcasecmp($a->getName(), $b->getName()));
        return $sponsors;
    }

    /**
     * @throws DoesNotExistException
     */
    public function find(int $id): Sponsor {
        return $this->mapper->find($id);
    }

    /**
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    public function create(array $data, string $uid): Sponsor {
        $sponsor = new Sponsor();
        $this->apply($sponsor, $data);
        $now = $this->time->getTime();
        $sponsor->setCreatedBy($uid);
        $sponsor->setCreatedAt($now);
        $sponsor->setUpdatedAt($now);
        return $this->mapper->insert($sponsor);
    }

    /**
     * @param array<string, mixed> $data
     * @throws ValidationException
     * @throws DoesNotExistException
     */
    public function update(int $id, array $data): Sponsor {
        $sponsor = $this->mapper->find($id);
        $this->apply($sponsor, $data);
        $sponsor->setUpdatedAt($this->time->getTime());
        return $this->mapper->update($sponsor);
    }

    /**
     * Links the sponsor to a donor (receipts go there) once it is known.
     */
    public function linkDonor(Sponsor $sponsor, int $donorId): Sponsor {
        if ($sponsor->getDonorId() === $donorId) {
            return $sponsor;
        }
        $sponsor->setDonorId($donorId);
        $sponsor->setUpdatedAt($this->time->getTime());
        return $this->mapper->update($sponsor);
    }

    /**
     * Only sponsors that were never written to can be deleted.
     *
     * @throws ValidationException
     * @throws DoesNotExistException
     */
    public function delete(int $id): void {
        $sponsor = $this->mapper->find($id);
        if ($this->inquiries->countBySponsor($id) > 0) {
            throw new ValidationException('Der Sponsor wurde schon angeschrieben und kann nicht gelöscht werden');
        }
        $this->mapper->delete($sponsor);
    }

    /**
     * Takes existing donors with e-mail address over as sponsors (linked to
     * them). Donors that already are sponsors are skipped.
     *
     * @param list<int> $donorIds
     * @return list<Sponsor> the new sponsors
     */
    public function importDonors(array $donorIds, string $uid): array {
        $created = [];
        foreach (array_unique($donorIds) as $donorId) {
            try {
                $donor = $this->donors->find((int)$donorId);
            } catch (DoesNotExistException) {
                continue;
            }
            if (trim((string)$donor->getEmail()) === '' || $this->mapper->findByDonor($donor->getId()) !== []) {
                continue;
            }
            $created[] = $this->create([
                'name' => $donor->getDisplayName(),
                'contact' => $donor->getKind() === Donor::KIND_PERSON ? $donor->getDisplayName() : '',
                'email' => $donor->getEmail(),
                'donorId' => $donor->getId(),
            ], $uid);
        }
        return $created;
    }

    /**
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    private function apply(Sponsor $sponsor, array $data): void {
        $values = [];
        foreach (self::FIELDS as $field) {
            $value = $data[$field] ?? '';
            if (!is_string($value)) {
                throw new ValidationException('Ungültiger Wert für ' . $field);
            }
            $value = trim($value);
            $values[$field] = $value === '' ? null : $value;
        }
        if ($values['name'] === null) {
            throw new ValidationException('Der Name ist erforderlich');
        }
        if ($values['email'] === null || !$this->mailer->validateMailAddress($values['email'])) {
            throw new ValidationException('Bitte eine gültige E-Mail-Adresse angeben');
        }
        foreach (['name' => 255, 'contact' => 255, 'salutation' => 255, 'email' => 255] as $field => $max) {
            if ($values[$field] !== null && mb_strlen($values[$field]) > $max) {
                throw new ValidationException('Der Wert für ' . $field . ' ist zu lang');
            }
        }

        $donorId = $data['donorId'] ?? null;
        if ($donorId !== null) {
            if (!is_int($donorId)) {
                throw new ValidationException('Ungültiger Spender');
            }
            try {
                $this->donors->find($donorId);
            } catch (DoesNotExistException) {
                throw new ValidationException('Der verknüpfte Spender existiert nicht');
            }
        }

        foreach ($values as $field => $value) {
            $sponsor->{'set' . ucfirst($field)}($value);
        }
        $sponsor->setDonorId($donorId);
    }
}
