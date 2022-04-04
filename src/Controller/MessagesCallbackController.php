<?php

namespace App\Controller;

use App\Dto\MessageCallback;
use App\Exception\ExceptionMessage;
use App\Service\MessageCallbackService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class MessagesCallbackController
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
class MessagesCallbackController extends Controller
{
    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function receiveAction(Request $request, MessageCallbackService $messageCallbackService): JsonResponse
    {
        if (! $request->isMethod('POST')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD);
        }

        $messageCallback = new MessageCallback(
            $request->get('ErrorCode'),
            $request->get('SmsSid'),
            $request->get('SmsStatus'),
            $request->get('MessageStatus'),
            $request->get('To'),
            $request->get('MessageSid'),
            $request->get('AccountSid'),
            $request->get('From'),
            $request->get('ApiVersion')
        );

        $messageCallbackService->handle($messageCallback);

        return $this->getResponse()->success(['status' => true]);
    }
}
