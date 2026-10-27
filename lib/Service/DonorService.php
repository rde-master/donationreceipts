<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\Db\Donor;
use OCA\DonationReceipts\Db\DonorMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Mail\IMailer;

class DonorService {
    private const FIELDS = ['firstName', 'lastName', 'company', 'street', 'zip', 'city', 'country', 'email', 'notes'];

    public function __construct(
        private DonorMapper $mapper,
        private IMailer $mailer,
        private ITimeFactory $time,
    ) {
    }

    /**
     * @return list<Donor> sorted by last name (persons) / name (companies)
     */
    public function search(string $term): array {
        $donors = $this->mapper->search($term);
        $collator = class_exists(\Collator::class) ? new \Collator('de_DE') : null;
        $key = static fn (Donor $d): string => $d->getKind() === Donor::KIND_COMPANY
            ? (string)$d->getCompany()
            : $d->getLastName() . ' ' . $d->getFirstName();
        usort($donors, static fn (Donor $a, Donor $b): int => $collator !== null
            ? $collator->compare($key($a), $key($b))
            : strcasecmp($key($a), $key($b)));
        return $donors;
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function find(int $id): Donor {
        return $this->mapper->find($id);
    }

    /**
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    public function create(array $data, string $uid): Donor {
        $donor = new Donor();
        $this->apply($donor, $data);
        $now = $this->time->getTime();
        $donor->setCreatedBy($uid);
        $donor->setCreatedAt($now);
        $donor->setUpdatedAt($now);
        return $this->mapper->insert($donor);
    }

    /**
     * @param array<string, mixed> $data
     * @throws ValidationException
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function update(int $id, array $data): Donor {
        $donor = $this->mapper->find($id);
        $this->apply($donor, $data);
        $donor->setUpdatedAt($this->time->getTime());
        return $this->mapper->update($donor);
    }

    /**
     * @throws \OCP\AppFramework\Db\DoesNotExistException
     */
    public function delete(int $id): void {
        $this->mapper->delete($this->mapper->find($id));
    }

    /**
     * @param array<string, mixed> $data
     * @throws ValidationException
     */
    private function apply(Donor $donor, array $data): void {
        $kind = $data['kind'] ?? $donor->getKind() ?? Donor::KIND_PERSON;
        if (!in_array($kind, [Donor::KIND_PERSON, Donor::KIND_COMPANY], true)) {
            throw new ValidationException('Ungültige Spenderart');
        }

        $values = [];
        foreach (self::FIELDS as $field) {
            $value = $data[$field] ?? '';
            if (!is_string($value)) {
                throw new ValidationException('Ungültiger Wert für ' . $field);
            }
            $value = trim($value);
            $values[$field] = $value === '' ? null : $value;
        }

        // Only the name fields of the chosen kind are kept.
        if ($kind === Donor::KIND_PERSON) {
            $values['company'] = null;
            if ($values['firstName'] === null || $values['lastName'] === null) {
                throw new ValidationException('Vorname und Nachname sind erforderlich');
            }
        } else {
            $values['firstName'] = null;
            $values['lastName'] = null;
            if ($values['company'] === null) {
                throw new ValidationException('Der Firmenname ist erforderlich');
            }
        }
        if ($values['email'] !== null && !$this->mailer->validateMailAddress($values['email'])) {
            throw new ValidationException('Die E-Mail-Adresse ist ungültig');
        }

        $donor->setKind($kind);
        foreach ($values as $field => $value) {
            $donor->{'set' . ucfirst($field)}($value);
        }
    }
}
