<?php

namespace App\Controller;

use App\Entity\FormsData;
use App\Entity\Users;
use App\Exception\ExceptionMessage;

class WorkspaceController extends Controller
{
    public function getFormsAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $account = $this->account();
        $accessLevel = $this->access();

        $organizationGeneral = $this->getDoctrine()
            ->getRepository('App:FormsData')
            ->getByModuleAccountAndAccessLevel('organization_general', $account, $accessLevel);

        $organizationOrganization = $this->getDoctrine()
            ->getRepository('App:FormsData')
            ->getByModuleAccountAndAccessLevel('organization_organization', $account, $accessLevel);


        return $this->getResponse()->success([
            'organization_general' => $organizationGeneral,
            'organization_organization' => $organizationOrganization
        ]);
    }
}
