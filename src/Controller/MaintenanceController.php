<?php


namespace App\Controller;

use App\Exception\ExceptionMessage;
use App\Service\GeneralSettingsService;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use function Sentry\captureException;

/**
 * Class MaintenanceController
 * @package App\Controller
 */
class MaintenanceController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function statusAction(GeneralSettingsService $generalSettingsService): JsonResponse
    {
        try {
            $maintenance = $generalSettingsService->getMaintenanceMode();

            return $this->getResponse()->success($maintenance);
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }
    }
}
