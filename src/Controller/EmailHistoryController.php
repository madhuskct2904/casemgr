<?php

namespace App\Controller;

use App\Entity\Users;
use App\Exception\ExceptionMessage;
use App\Service\EmailHistoryService;

class EmailHistoryController extends Controller
{
    public function indexAction(EmailHistoryService $emailHistoryService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $emailHistoryEntries = $this->getDoctrine()->getRepository('App:EmailMessage')->findAll();
        $index = $emailHistoryService->prepareIndex($emailHistoryEntries);

        return $this->getResponse()->success(['history' => $index]);
    }
}
