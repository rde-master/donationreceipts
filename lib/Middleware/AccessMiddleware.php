<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Middleware;

use OCA\DonationReceipts\Attribute\AccessGroupExempt;
use OCA\DonationReceipts\Exception\AccessDeniedException;
use OCA\DonationReceipts\Service\AccessService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Middleware;
use OCP\IUserSession;
use ReflectionClass;

/**
 * Server-side group check for every request to this app. Default-deny:
 * every controller is restricted unless its class carries
 * #[AccessGroupExempt], so a newly added controller can't be forgotten.
 */
class AccessMiddleware extends Middleware {
    public function __construct(
        private AccessService $accessService,
        private IUserSession $userSession,
    ) {
    }

    public function beforeController(Controller $controller, string $methodName): void {
        $reflection = new ReflectionClass($controller);
        if ($reflection->getAttributes(AccessGroupExempt::class) !== []) {
            return;
        }
        if (!$this->accessService->isAllowed($this->userSession->getUser())) {
            throw new AccessDeniedException();
        }
    }

    public function afterException(Controller $controller, string $methodName, \Exception $exception): Response {
        if (!$exception instanceof AccessDeniedException) {
            throw $exception;
        }
        if (str_starts_with($methodName, 'index')) {
            return new TemplateResponse('core', '403', ['message' => $exception->getMessage()], TemplateResponse::RENDER_AS_GUEST, Http::STATUS_FORBIDDEN);
        }
        return new JSONResponse(['error' => $exception->getMessage()], Http::STATUS_FORBIDDEN);
    }
}
