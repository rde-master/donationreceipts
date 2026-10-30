<?php

declare(strict_types=1);

namespace OCA\DonationReceipts\Controller;

use OCA\DonationReceipts\AppInfo\Application;
use OCA\DonationReceipts\Attribute\AccessGroupExempt;
use OCA\DonationReceipts\Exception\ValidationException;
use OCA\DonationReceipts\Service\InquiryService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\AnonRateLimit;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Template\PublicTemplateResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use OCP\Util;

/**
 * The public response form behind the personal link in a sponsor letter.
 * No login: the 32-character token is the only key, so unknown tokens are
 * throttled (brute force protection). Exempt from the group check.
 */
#[AccessGroupExempt]
class ResponseController extends Controller {
    private const BRUTE_FORCE_ACTION = 'donationreceipts_response';

    public function __construct(
        string $appName,
        IRequest $request,
        private InquiryService $inquiries,
        private IInitialState $initialState,
    ) {
        parent::__construct($appName, $request);
    }

    #[PublicPage]
    #[NoCSRFRequired]
    #[BruteForceProtection(action: self::BRUTE_FORCE_ACTION)]
    #[AnonRateLimit(limit: 60, period: 600)]
    public function show(string $token): TemplateResponse {
        try {
            $view = $this->inquiries->publicView($token);
        } catch (DoesNotExistException) {
            $response = new TemplateResponse('core', '404', ['message' => 'Dieser Link ist ungültig.'], TemplateResponse::RENDER_AS_GUEST, Http::STATUS_NOT_FOUND);
            $response->throttle();
            return $response;
        }
        $this->initialState->provideInitialState('response', $view + ['token' => $token]);
        Util::addScript(Application::APP_ID, Application::APP_ID . '-public');
        $response = new PublicTemplateResponse(Application::APP_ID, 'public');
        $response->setHeaderTitle($view['campaign']);
        $response->setHeaderDetails($view['org']);
        return $response;
    }

    /**
     * Saves the response. Sent as multipart form: "data" (JSON) and an
     * optional file "logo".
     */
    #[PublicPage]
    #[BruteForceProtection(action: self::BRUTE_FORCE_ACTION)]
    #[AnonRateLimit(limit: 20, period: 600)]
    public function submit(string $token): JSONResponse {
        $data = json_decode((string)$this->request->getParam('data', ''), true);
        if (!is_array($data)) {
            return new JSONResponse(['error' => 'Ungültige Eingabe'], Http::STATUS_BAD_REQUEST);
        }
        $logo = null;
        $file = $this->request->getUploadedFile('logo');
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                return new JSONResponse(['error' => 'Das Hochladen des Logos ist fehlgeschlagen (höchstens 10 MB)'], Http::STATUS_BAD_REQUEST);
            }
            $logo = ['content' => (string)file_get_contents($file['tmp_name']), 'name' => (string)$file['name']];
        }
        try {
            return new JSONResponse($this->inquiries->respond($token, $data, $logo));
        } catch (ValidationException $e) {
            return new JSONResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
        } catch (DoesNotExistException) {
            $response = new JSONResponse(['error' => 'Dieser Link ist ungültig.'], Http::STATUS_NOT_FOUND);
            $response->throttle();
            return $response;
        }
    }
}
