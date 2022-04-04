<?php

namespace App\Controller;

use App\Entity\Forms;
use App\Entity\FormsData;
use App\Entity\FormsValues;
use App\Enum\ParticipantStatus;
use App\Enum\ParticipantType;
use App\Exception\ExceptionMessage;
use App\Service\AssignmentFormsService;
use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class ModulesController
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
class ModulesController extends Controller
{


    /**
     * @return JsonResponse
     * @api {post} /modules/forms Get Module Forms
     * @apiGroup Modules
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String} group Module group
     * @apiParam {String} key Module Key
     * @apiParam {Integer} element_id User Id
     * @apiParam {Integer} assignment_id Assignment Id
     *
     * @apiSuccess {Array} data Results
     *
     * @apiError message Error Message
     *
     */
    public function FormsAction(): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $account = $this->account();
        $participantType = $account->getParticipantType();
        $config = $this->getParameter('modules');

        if ($participantType == ParticipantType::INDIVIDUAL) {
            $config = $config['participant_forms'];
        }

        if ($participantType == ParticipantType::MEMBER) {
            $config = $config['member_forms'];
        }

        $group = $this->getRequest()->param('group');

        if (!isset($config) || !isset($config[$group])) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_CONFIG, 401);
        }

        $elementId = $this->getRequest()->param('element_id');
        $assignmentId = $this->getRequest()->param('assignment_id');

        $modules = $this->getDoctrine()->getRepository('App:Modules')->findBy(['key' => $config[$group]]);

        $formsOrder = array_flip($config[$group]);

        foreach ($modules as $module) {
            $sortedModules[$formsOrder[$module->getKey()]] = $module;
        }

        foreach ($sortedModules as $moduleIdx => $module) {
            $forms = $this->getDoctrine()->getRepository('App:Forms')->findByModuleAndAccount($module, $account, false, true);

            $data['forms'][$moduleIdx + 1] = [
                'id' => $module->getId(),
                'group' => $module->getGroup(),
                'key' => $module->getKey(),
                'name' => $module->getName(),
                'role' => $module->getRole(),
                'forms' => [],
            ];

            /** @var Forms $form */
            foreach ($forms as $form) {
                $formData = $this->getDoctrine()->getRepository('App:FormsData')->findOneBy([
                    'module' => $module,
                    'form' => $form,
                    'element_id' => $elementId,
                    'assignment' => $assignmentId
                ], ['id' => 'DESC']);

                // Array with values
                $values = [];
                if ($formData !== null) {
                    /** @var FormsValues $value */
                    $get_values = $this->getDoctrine()->getRepository('App:FormsValues')->findByData($formData);

                    foreach ($get_values as $value) {
                        $values[$value->getName()] = $value->getValue();
                    }

                    $fields = json_decode($form->getData(), true, 512, JSON_THROW_ON_ERROR);
                    foreach ($fields as $field) {
                        if (strpos($field['type'], 'shared-field') !== false) {
                            $values[$field['name']] = $this->findValueBySharedField($field['name'], $formData);
                        }
                    }
                }


                $data['forms'][$moduleIdx + 1]['forms'][] = [
                    'id' => $form->getId(),
                    'name' => $form->getName(),
                    'description' => $form->getDescription(),
                    'conditionals' => json_decode($form->getConditionals(), true),
                    'calculations' => json_decode($form->getCalculations(), true),
                    'system_conditionals' => json_decode($form->getSystemConditionals(), true),
                    'extra_validation_rules' => json_decode($form->getExtraValidationRules(), true),
                    'data' => json_decode($form->getData(), true),
                    'data_id' => $formData === null ? null : $formData->getId(),
                    'values' => $values,
                    'columns_map' => json_decode($form->getColumnsMap(), true),
                    'hide_values' => $form->getHideValues()
                ];
            }
        }

        $data['is_latest_dismissed_assignment'] = false;

        if ($assignmentId) {
            if ($assignment = $this->getDoctrine()->getRepository('App:Assignments')->findOneBy(['id' => $assignmentId])) {
                $data['history'] = [
                    'assignment' => [
                        'id' => $assignment->getId(),
                        'programStatusStartDate' => $assignment->getProgramStatusStartDate(),
                        'programStatusEndDate' => $assignment->getProgramStatusEndDate(),
                        'programStatus' => $assignment->getProgramStatus(),
                        'primaryCaseManager' => $assignment->getPrimaryCaseManager() ? $assignment->getPrimaryCaseManager()->getData()->getFullName(false) : '',
                        'avatar' => $assignment->getAvatar()
                    ],
                ];
            }
            $latestAssignment = $this->getDoctrine()->getRepository('App:Assignments')->findLatestAssignmentForParticipant($elementId);

            if ($latestAssignment && $latestAssignment->getId() == $assignmentId) {
                $data['is_latest_dismissed_assignment'] = true;
            }
        }

        $module = $this->getDoctrine()->getRepository('App:Modules')->findOneBy([
            'key' => 'participants_assignment'
        ]);

        $currentAssignment = $this->getDoctrine()->getRepository('App:FormsData')->findBy([
            'module' => $module,
            'element_id' => $elementId,
            'assignment' => null
        ], ['id' => 'DESC']);

        $data['has_active_assignment'] = (bool)$currentAssignment;

        $programs = $account->getPrograms();
        $programsArr = [];

        if ($programs) {
            foreach ($programs as $program) {
                $programsArr[] = [
                    'id' => $program->getId(),
                    'name' => $program->getName(),
                    'status' => $program->getStatus()
                ];
            }
        }

        $data['programs'] = $programsArr;

        return $this->getResponse()->success($data);
    }

    private function findValueBySharedField(string $sharedFieldName, FormsData $formsData): ?string
    {
        $sharedField = $this->getDoctrine()->getRepository('App:SharedField')->findOneBy(['fieldName' => $sharedFieldName]);

        $fieldValue = $this->getDoctrine()->getRepository('App:FormsValues')->findByNameAndDataElementId(
            $sharedField->getSourceFieldName(),
            $formsData->getElementId()
        );

        return $fieldValue ? $fieldValue->getValue() : null;
    }

    public function confirmCurrentAssignmentOverwriteAction(AssignmentFormsService $assignmentFormsService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $dataId = $this->getRequest()->param('dataId');

        $module = $this->getDoctrine()->getRepository('App:Modules')->findOneBy([
            'key' => 'participants_assignment'
        ]);

        $formData = $this->getDoctrine()->getRepository('App:FormsData')->find($dataId);
        $participantId = $formData->getElementId();

        $currentAssignment = $this->getDoctrine()->getRepository('App:FormsData')->findBy([
            'module' => $module,
            'element_id' => $participantId,
            'assignment' => null
        ], ['id' => 'DESC']);

        if ($currentAssignment[0]->getId() === $dataId) {
            return $this->getResponse()->success(['must_confirm' => false]);
        }

        if (!(bool)$currentAssignment) {
            return $this->getResponse()->success(['must_confirm' => false]);
        }

        $newFormData = $this->getRequest()->param('formData');
        $systemConditionals = json_decode($formData->getForm()->getSystemConditionals(), true);

        if (!isset($systemConditionals['programStatus'])) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_FORM, 422);
        }

        $newProgramStatus = '';

        foreach ($newFormData as $item) {
            if ($item['name'] == $systemConditionals['programStatus']['field']) {
                $newProgramStatus = $item['value'];
            }
        }

        $assignmentFormsService->setForm($formData->getForm());

        try {
            $newParticipantStatus = $assignmentFormsService->getParticipantStatusByProgramStatus($newProgramStatus);
        } catch (\Exception $e) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_PROGRAM_STATUS, 422);
        }

        if ($newParticipantStatus == ParticipantStatus::DISMISSED) {
            return $this->getResponse()->success(['must_confirm' => false]);
        }

        return $this->getResponse()->success(['must_confirm' => true]);
    }
}
