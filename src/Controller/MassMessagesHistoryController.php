<?php

namespace App\Controller;

use App\Exception\ExceptionMessage;
use App\Service\MassMessageHistoryService;
use App\Utils\Helper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class MassMessagesHistoryController
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
class MassMessagesHistoryController extends Controller
{

    /**
     * @return JsonResponse
     */
    public function searchAction(MassMessageHistoryService $massMessageHistoryService): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $result                    = $massMessageHistoryService->search(
            $this->getRequest()->params(),
            $this->user()
        );

        return $this->getResponse()->success($result);
    }

    /**
     * @param int $id
     *
     * @return JsonResponse
     */
    public function detailsAction(int $id, MassMessageHistoryService $massMessageHistoryService): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $result                    = $massMessageHistoryService->searchMessages(
            $id,
            $this->getRequest()->params(),
            $this->user()
        );

        return $this->getResponse()->success($result);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse|Response
     */
    public function exportAction(Request $request, MassMessageHistoryService $massMessageHistoryService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($request->isMethod('GET') == false) {
            return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 401);
        }


        $id = $request->query->get('id');

        if (! $massMessage = $this->getDoctrine()->getRepository('App:MassMessages')->findOneBy([
            'id' => $id
        ])) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_MASS_MESSAGE, 401);
        }

        $csv = $massMessageHistoryService->getCsv($massMessage);

        $file_name = 'Message_History';

        return new Response(
            (Helper::csvConvert($csv)),
            200,
            [
                'Content-Type'        => 'application/csv',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $file_name . '.csv'),
            ]
        );
    }
}
