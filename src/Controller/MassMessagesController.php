<?php

namespace App\Controller;

use App\Exception\ExceptionMessage;
use App\Service\MassMessageService;
use App\Utils\Helper;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use function Sentry\captureException;

/**
 * Class MassMessagesController
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
class MassMessagesController extends Controller
{
    public function sendAction(MassMessageService $massMessageService): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $body           = $this->getRequest()->param('body', null);
        $participantIds = $this->getRequest()->param('participant_ids');

        if (! is_string($body) || strlen($body) < 1 || strlen($body) > 255) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_MESSAGE_LENGTH);
        }
        if (! $accountPhone = $this->account()->getTwilioPhone()) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PHONE_NUMBER, 400);
        }
        $fromNumber = Helper::convertPhone($accountPhone);
        if (! $fromNumber) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_PHONE_NUMBER);
        }

        $massMessageService->setFromNumber($fromNumber);

        try {
            $result = $massMessageService->sendMessage(
                $this->user(),
                $participantIds,
                $body
            );

            return $this->getResponse()->success(['message' => $result]);
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }
    }
}
