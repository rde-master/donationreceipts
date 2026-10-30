<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Service;

use OCA\DonationReceipts\Db\LetterTemplate;
use OCA\DonationReceipts\Db\LetterTemplateMapper;
use OCA\DonationReceipts\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Templates for letters to sponsors, maintained by the group.
 */
class LetterTemplateService {
    public function __construct(
        private LetterTemplateMapper $mapper,
        private ITimeFactory $time,
    ) {
    }

    /**
     * @return list<LetterTemplate>
     */
    public function list(): array {
        return $this->mapper->findAll();
    }

    /**
     * @throws ValidationException
     */
    public function create(string $name, string $subject, string $body): LetterTemplate {
        $template = new LetterTemplate();
        $this->apply($template, $name, $subject, $body);
        $now = $this->time->getTime();
        $template->setCreatedAt($now);
        $template->setUpdatedAt($now);
        return $this->mapper->insert($template);
    }

    /**
     * @throws ValidationException
     * @throws DoesNotExistException
     */
    public function update(int $id, string $name, string $subject, string $body): LetterTemplate {
        $template = $this->mapper->find($id);
        $this->apply($template, $name, $subject, $body);
        $template->setUpdatedAt($this->time->getTime());
        return $this->mapper->update($template);
    }

    /**
     * @throws DoesNotExistException
     */
    public function delete(int $id): void {
        $this->mapper->delete($this->mapper->find($id));
    }

    /**
     * @throws ValidationException
     */
    private function apply(LetterTemplate $template, string $name, string $subject, string $body): void {
        $name = trim($name);
        $subject = trim($subject);
        if ($name === '') {
            throw new ValidationException('Der Name der Vorlage fehlt');
        }
        if ($subject === '') {
            throw new ValidationException('Der Betreff fehlt');
        }
        if (mb_strlen($name) > 128 || mb_strlen($subject) > 255) {
            throw new ValidationException('Name oder Betreff ist zu lang');
        }
        $template->setName($name);
        $template->setSubject($subject);
        $template->setBody($body);
    }
}
