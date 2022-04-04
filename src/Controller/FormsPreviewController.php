<?php

namespace App\Controller;

use App\Domain\FormData\FormDataTableHelper;
use App\Exception\ExceptionMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class FormsPreviewController extends Controller
{
    public function previewAction(Request $request, FormDataTableHelper $formDataTableHelper): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $moduleKey = $request->get('module');
        $elementId = $request->get('element_id');
        $assignmentId = $request->get('assignment_id');
        $formId = $request->get('form_id');

        $module = $this->getDoctrine()->getRepository('App:Modules')->findOneBy(['key' => $moduleKey]);

        if ($module === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_MODULE_KEY);
        }

        if (in_array($moduleKey, ['participants_profile','participants_contact','participants_assignment','activities_services','assessment_outcomes'])) {
            if (!$elementId) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_MODULE_KEY_OR_ELEMENT_ID);
            }

            $form = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($formId, $this->account(), $this->access());

            $formDataCriteria = [
                'module'     => $module,
                'form'       => $form,
                'assignment' => $assignmentId,
                'element_id' => $elementId
            ];

            $completedForms = $this->getDoctrine()->getRepository('App:FormsData')->findBy($formDataCriteria, ['created_date' => 'DESC']);
        }

        if (in_array($moduleKey, ['organization_organization', 'organization_general'])) {
            $account = $this->account();
            $accessLevel = $this->access();

            $form = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($formId, $account, $accessLevel);

            $completedForms = $this->getDoctrine()->getRepository('App:FormsData')->findByFormAndAccount($form, $account);
        }

        $formDataTableHelper->setForm($form);
        $formDataTableHelper->setFormDataEntries($completedForms);
        $formDataTableHelper->setDateFormat($this->phpDateFormat());

        $result = [
            'id'      => $form->getId(),
            'name'    => $form->getName(),
            'columns' => $formDataTableHelper->getColumns(),
            'rows'    => $formDataTableHelper->getRows(),
            'publish' => $form->getPublish()
        ];

        return $this->getResponse()->success($result);
    }
}
