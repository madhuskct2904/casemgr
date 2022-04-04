<?php


namespace App\Controller;

use App\Entity\Users;
use App\Exception\ExceptionMessage;
use App\Service\GeneralSettingsService;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class GeneralSettingsController
 *
 * @IgnoreAnnotation("api")
 * @IgnoreAnnotation("apiGroup")
 * @IgnoreAnnotation("apiHeader")
 * @IgnoreAnnotation("apiParam")
 * @IgnoreAnnotation("apiSuccess")
 * @IgnoreAnnotation("apiError")
 *
 * @package App\Controller
 */
class GeneralSettingsController extends Controller
{
    /**
     * @return JsonResponse
     * @throws Exception
     */
    public function getAllSettingsAction(GeneralSettingsService $generalSettingsService): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $data = $generalSettingsService->getAllSettings();

        return $this->getResponse()->success($data);
    }

    /**
     * @return JsonResponse
     * @throws Exception
     */
    public function saveAction(GeneralSettingsService $generalSettingsService): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $generalSettingsService->save($this->getRequest()->param('data'));

        return $this->getResponse()->success(['message' => 'General settings saved']);
    }
}
