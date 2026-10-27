<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Exception;

class AccessDeniedException extends \Exception {
    public function __construct() {
        parent::__construct('Kein Zugriff auf Spendenbescheinigungen');
    }
}
