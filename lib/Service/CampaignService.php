<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\Db\Campaign;
use OCA\DonationReceipts\Db\CampaignMapper;
use OCA\DonationReceipts\Db\InquiryMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Campaigns ("Sommerfest 2027") group the letters to sponsors and their
 * responses.
 */
class CampaignService {
    public function __construct(
        private CampaignMapper $mapper,
        private InquiryMapper $inquiries,
        private ITimeFactory $time,
    ) {
    }

    /**
     * @return list<Campaign> newest first
     */
    public function list(): array {
        return $this->mapper->findAll();
    }

    /**
     * @throws DoesNotExistException
     */
    public function find(int $id): Campaign {
        return $this->mapper->find($id);
    }

    /**
     * @throws ValidationException
     */
    public function create(string $name, string $uid): Campaign {
        $campaign = new Campaign();
        $campaign->setName($this->validName($name));
        $campaign->setClosed(false);
        $now = $this->time->getTime();
        $campaign->setCreatedBy($uid);
        $campaign->setCreatedAt($now);
        $campaign->setUpdatedAt($now);
        return $this->mapper->insert($campaign);
    }

    /**
     * @throws ValidationException
     * @throws DoesNotExistException
     */
    public function update(int $id, string $name, bool $closed): Campaign {
        $campaign = $this->mapper->find($id);
        $campaign->setName($this->validName($name));
        $campaign->setClosed($closed);
        $campaign->setUpdatedAt($this->time->getTime());
        return $this->mapper->update($campaign);
    }

    /**
     * Only campaigns without letters can be deleted.
     *
     * @throws ValidationException
     * @throws DoesNotExistException
     */
    public function delete(int $id): void {
        $campaign = $this->mapper->find($id);
        if ($this->inquiries->countByCampaign($id) > 0) {
            throw new ValidationException('Zu der Aktion gibt es schon Anschreiben. Schließe sie stattdessen.');
        }
        $this->mapper->delete($campaign);
    }

    /**
     * @throws ValidationException
     */
    private function validName(string $name): string {
        $name = trim($name);
        if ($name === '') {
            throw new ValidationException('Der Name der Aktion fehlt');
        }
        if (mb_strlen($name) > 255) {
            throw new ValidationException('Der Name der Aktion ist zu lang');
        }
        return $name;
    }
}
