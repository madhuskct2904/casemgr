<?php

namespace App\Controller;

use App\Exception\ExceptionMessage;
use Symfony\Component\HttpFoundation\JsonResponse;

class CaseManagersController extends Controller
{


    public function indexAction(): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $managers = $this->getDoctrine()->getRepository('App:Credentials')->getCaseManagers($this->account(), false);

        return $this->getResponse()->success([
            'managers' => $managers,
        ]);
    }

    public function getManagerDataAction(int $managerUserId): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $data = $this->getDoctrine()->getRepository('App:Credentials')->getCaseManager($managerUserId, $this->account());

        return $this->getResponse()->success([
            'data' => $data,
        ]);

    }
}
