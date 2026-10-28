<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\Service\StatsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;

class StatsController extends Controller {
    public function __construct(
        string $appName,
        IRequest $request,
        private StatsService $stats,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function index(?int $year = null): JSONResponse {
        return new JSONResponse($this->stats->overview($year));
    }
}
