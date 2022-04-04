<?php

namespace App\Controller;

use App\Entity\Programs;
use App\Entity\Users;
use App\Exception\ExceptionMessage;

class ProgramsController extends Controller
{
    public function createAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $em = $this->getDoctrine()->getManager();

        $accountId = $this->getRequest()->param('account_id');

        $account = $em->getRepository('App:Accounts')->find($accountId);

        if (!$account) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_ACCOUNT);
        }

        $programName = $this->getRequest()->param('name');
        $program = new Programs();
        $program->setAccount($account);
        $program->setName($programName);
        $program->setStatus(0);
        $program->setCreationDate(new \DateTime);

        $em->persist($program);
        $em->flush();

        return $this->getResponse()->success(['program_id' => $program->getId()]);
    }

    public function updateAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $programId = $this->getRequest()->param('id');
        $program = $this->getDoctrine()->getRepository('App:Programs')->find($programId);

        if (!$this->canUserAccessAccount()) {
            return $this->getResponse()->error(ExceptionMessage::ACCOUNT_UNAUTHORIZED);
        }

        if ($this->getRequest()->hasParam('name')) {
            $program->setName($this->getRequest()->param('name'));
        }

        if ($this->getRequest()->hasParam('status')) {
            $program->setStatus($this->getRequest()->param('status'));
        }

        $em = $this->getDoctrine()->getManager();
        $em->flush();

        return $this->getResponse()->success(['message'=>'Program updated!']);
    }

    private function canUserAccessAccount()
    {
        $authorizedAccounts = $this->user()->getAccounts();
        $thisAccount = $this->account();

        return $authorizedAccounts->contains($thisAccount);
    }
}
