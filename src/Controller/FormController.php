<?php

namespace App\Controller;

use App\Domain\Form\BatchCalculationsHelper;
use App\Domain\Form\FormSchemaHelper;
use App\Domain\Form\ProgramsService;
use App\Domain\Form\SharedFieldsService;
use App\Domain\Form\SharedFormPreviewsHelper;
use App\Entity\Users;
use App\Enum\AccountType;
use App\Enum\FormType;
use App\Event\FormDataRemovedEvent;
use App\Exception\ExceptionMessage;
use App\Service\AccountFormsService;
use App\Service\FormBuilderService;
use App\Service\Participants\ParticipantDirectoryColumnsService;
use App\Service\Reports\ReportsLabelsService;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class FormController extends Controller
{
    public function getByIdAction(SharedFieldsService $sharedFieldsService, SharedFormPreviewsHelper $sharedFormPreviewsHelper): JsonResponse
    {
        // $assignmentId is used for taking shared form values from historical assignment not from current
        $assignmentId = $this->getRequest()->param('assignment_id') ?? null;

        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $formId = $this->getRequest()->param('id');

        if (!$formId) {
            return $this->getResponse()->error(ExceptionMessage::MISSING_FORM_ID, 400);
        }

        if (!$form = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($formId, $this->account(), $this->access())) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_FORM, 404);
        }

        $accounts = $form->getAccounts();
        $accountsArr = [];

        foreach ($accounts as $account) {
            $accountsArr[] = [
                'id'               => $account->getId(),
                'organizationName' => $account->getOrganizationName(),
                'systemId'         => $account->getSystemId()
            ];
        }

        if ($this->account()->getAccountType() == AccountType::CHILD) {
            $accountId = $this->account()->getId();
            if (!in_array($accountId, array_column($accountsArr, 'id'))) {
                return $this->getResponse()->error(ExceptionMessage::NO_ACCESS);
            }
        }

        $programs = $form->getPrograms();
        $programsArr = [];

        foreach ($programs as $program) {
            $programsArr[] = [
                'id'   => $program->getId(),
                'name' => $program->getName()
            ];
        }

        $accessLevels = array_flip(Users::ACCESS_LEVELS);
        $lastModificationDate = $form->getLastActionDate();
        $module = $form->getModule();

        $participantUserId = $this->getRequest()->param('participant_user_id', null);

        // values - pre-rendered values. For now only "shared fields" may have set values
        // $assignmentId passed for taking shared form values from historical assignment not from current
        $values = $sharedFieldsService->getSharedFieldsValues($form, $this->account(), $participantUserId, $assignmentId);

        // form previews for form builder

        $formPreviews = $sharedFormPreviewsHelper->getFormPreviews($form, $this->account(), $participantUserId, null);
        $sharedFields = $sharedFieldsService->getSharedFields($form);

        if (!count($sharedFields)) {
            $sharedFields = new \stdClass();
        }

        return $this->getResponse()->success([
            'name'                   => $form->getName(),
            'description'            => $form->getDescription(),
            'data'                   => $form->getData(),
            'type'                   => $form->getType(),
            'user'                   => $form->getUser()->getData()->getFullName(),
            'created_date'           => $form->getCreatedDate(),
            'last_action_user'       => $form->getLastActionUser() === null ? '' : $form->getLastActionUser()->getData()->getFullName(),
            'last_modification_date' => $lastModificationDate === null ? '' : $lastModificationDate->format('Y-m-d H:i:s'),
            'status'                 => $form->getStatus(),
            'publish'                => $form->getPublish(),
            'conditionals'           => $form->getConditionals(),
            'calculations'           => $form->getCalculations(),
            'system_conditionals'    => $form->getSystemConditionals(),
            'update_conditionals'    => $form->getUpdateConditionals(),
            'hide_values'            => $form->getHideValues() ?: '[]',
            'columns_map'            => $form->getColumnsMap(),
            'access_level'           => $form->getAccessLevel() ? $accessLevels[$form->getAccessLevel()] : null,
            'multiple_entries'       => $form->getMultipleEntries(),
            'custom_columns'         => $form->getCustomColumns(),
            'accounts'               => $accountsArr,
            'form_programs'          => $programsArr,
            'share_uid'              => $form->getShareUid(),
            'share_with_participant' => $form->isSharedWithParticipant(),
            'has_shared_fields'      => $form->hasSharedFields(),
            'extra_validation_rules' => $form->getExtraValidationRules(),
            'captcha_enabled'        => $form->isCaptchaEnabled(),
            'module'                 => $module === null ? [] : [
                'id'          => $module->getId(),
                'key'         => $module->getKey(),
                'group'       => $module->getGroup(),
                'name'        => $module->getName(),
                'role'        => $module->getRole(),
                'columns_map' => $module->getColumnsMap()
            ],
            'shared_fields'          => $sharedFields,
            'values'                 => $values,
            'form_previews'          => $formPreviews
        ]);
    }

    /**
     * Get simplified forms index for Reports View. Get forms only by given ids.
     */
    public function getByIdsAction(): JsonResponse
    {

        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $ids = $this->getRequest()->param('ids');
        $data = [];

        if (is_string($ids)) {
            $ids = json_decode($ids, true);
        }

        foreach ($ids as $id) {
            if ($repository = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($id, $this->account(), $this->access())) {
                $data[] = [
                    'id'         => $repository->getId(),
                    'name'       => $repository->getName(),
                    'module'     => $repository->getModule()->getId(),
                    'module_key' => $repository->getModule()->getKey(),
                    'data'       => json_decode($repository->getData(), true),
                ];
            }
        }

        return $this->getResponse()->success($data);
    }


    public function createAction(SharedFieldsService $sharedFieldsService, AccountFormsService $accountFormsService): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $name = $this->getRequest()->param('name');
        $description = $this->getRequest()->param('description', '');
        $data = $this->getRequest()->param('data');
        $conditionals = $this->getRequest()->param('conditionals', json_encode([]));
        $systemConditionals = $this->getRequest()->param('system_conditionals', json_encode([]));
        $updateConditionals = $this->getRequest()->param('update_conditionals', json_encode([]));
        $hideValues = $this->getRequest()->param('hide_values', json_encode([]));
        $calculations = $this->getRequest()->param('calculations', json_encode([]));
        $columnsMap = $this->getRequest()->param('columns_map', '');

        $type = $this->getRequest()->param('type');
        $module = $this->getRequest()->param('module');
        $accessLevel = $this->getRequest()->param('access_level');

        $multipleEntries = $this->getRequest()->param('multiple_entries', true);
        $captchaEnabled = $this->getRequest()->param('captcha_enabled', false);

        if ($module !== null) {
            $module = $this->getDoctrine()->getRepository('App:Modules')->findOneByKey($module);
        }

        if (!$module && $type != FormType::TEMPLATE) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_MODULE, 400);
        }

        if ($accessLevel && isset(Users::ACCESS_LEVELS[($accessLevel)])) {
            $accessLevel = Users::ACCESS_LEVELS[($accessLevel)];
        }

        if ($this->account()->isMain() && $type === FormType::TEMPLATE) {
            $account = null;
        } else {
            $account = $this->account();
        }

        $newForm = $this->getDoctrine()->getRepository('App:Forms')->save(
            $this->user(),
            $name,
            $description,
            $data,
            $conditionals,
            $systemConditionals,
            $updateConditionals,
            $calculations,
            $columnsMap,
            $type,
            $module,
            $multipleEntries,
            $account,
            $accessLevel,
            $hideValues,
            $captchaEnabled
        );

        if ($account->getAccountType() === AccountType::PARENT) {
            $accountFormsService->addFormToChildAccounts($newForm, $account);
        }

        $sharedFields = $this->getRequest()->param('shared_fields', []);
        $sharedFieldsService->updateSharedFields($newForm, $sharedFields, $this->phpDateFormat($this->user()));

        return $this->getResponse()->success(['message' => 'Form added', 'id' => $newForm->getId()]);
    }

    public function updateAction(
        SharedFieldsService $sharedFieldsService,
        FormBuilderService $formBuilderService,
        ParticipantDirectoryColumnsService $pdcService,
        ProgramsService $programsService,
        ReportsLabelsService $reportsLabelsService
    ): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $formId = $this->getRequest()->param('id');

        if (!$formId) {
            return $this->getResponse()->error(ExceptionMessage::MISSING_FORM_ID, 400);
        }

        $name = $this->getRequest()->param('name', '');
        $description = $this->getRequest()->param('description', '');

        $schema = $this->getRequest()->param('data');
        $schema1 = json_decode($schema, true);
        $output = array();

        foreach($schema1 as $key=>$s)
        {
            if (strpos($s['description'], '(copy)') !== false) { //PAY ATTENTION TO !==, not !=
                $replaced = str_replace('(copy)', "",$s['description']);
                $s['description'] = trim($replaced);
               
            }
            $schema1[$key]['description'] = $s['description'];
        }
        $schema = json_encode($schema1, true);
        $conditionals = $this->getRequest()->param('conditionals', json_encode([]));
        $systemConditionals = $this->getRequest()->param('system_conditionals', json_encode([]));
        $hideValues = $this->getRequest()->param('hide_values', json_encode([]));
        $calculations = $this->getRequest()->param('calculations', json_encode([]));
        $columnsMap = $this->getRequest()->param('columns_map', '');
        $customColumns = $this->getRequest()->param('custom_columns', json_encode([]));
        $type = $this->getRequest()->param('type');
        $module = $this->getRequest()->param('module');
        $accessLevel = $this->getRequest()->param('access_level');
        $accounts = $this->getRequest()->param('accounts');
        $multipleEntries = $this->getRequest()->param('multiple_entries', true);
        $formPrograms = $this->getRequest()->param('form_programs', []);
        $shareWithParticipant = $this->getRequest()->param('share_with_participant', false);
        $hasSharedFields = $this->getRequest()->param('has_shared_fields', false);
        $captchaEnabled = $this->getRequest()->param('captcha_enabled', false);
        $extraValidationRules = $this->getRequest()->param('extra_validation_rules', json_encode([]));
        $updateConditionals = $this->getRequest()->param('update_conditionals', json_encode([]));

        if ($module !== null) {
            $module = $this->getDoctrine()->getRepository('App:Modules')->findOneByKey($module);
        }

        if (!$module && $type != FormType::TEMPLATE) {
            return $this->getResponse()->error(ExceptionMessage::WRONG_MODULE, 400);
        }

        if ($accessLevel && isset(Users::ACCESS_LEVELS[($accessLevel)])) {
            $accessLevel = Users::ACCESS_LEVELS[($accessLevel)];
        }

        $form = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($formId, $this->account(), $this->access());

        if (!$form) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_FORM, 404);
        }

        $oldSchema = $form->getData();
        $oldCalculations = $form->getCalculations();
        $setGlobal = false;

        $oldSharedFieldsArr = $sharedFieldsService->getSharedFields($form);

        $sharedFields = $this->getRequest()->param('shared_fields', []);

        if ($this->account()->isMain() && $type === 'template') {
            $setGlobal = true;
        }

        $this->getDoctrine()->getRepository('App:Forms')->update(
            $form,
            $this->user(),
            $name,
            $description,
            $schema,
            $conditionals,
            $extraValidationRules,
            $systemConditionals,
            $updateConditionals,
            $calculations,
            $columnsMap,
            $type,
            $module,
            $multipleEntries,
            $setGlobal,
            $accessLevel,
            $hideValues,
            $customColumns,
            $captchaEnabled,
            $shareWithParticipant,
            $hasSharedFields
        );

        $oldSchemaArr = json_decode($oldSchema, true);
        $newSchemaArr = json_decode($schema, true);

        $formBuilderService->updateExistingFormsData($form, $oldSchemaArr, $newSchemaArr);

        if ($form->getType() == FormType::FORM && in_array($form->getModule()->getGroup(), ['multiple', 'organization'])) {
            $form->clearAccounts();
            $accounts = $this->getDoctrine()->getRepository('App:Accounts')->findBy(['id' => array_column($accounts, 'id')]);

            if (!count($accounts) && $form->getModule()->getRole() === 'referral') {
                $accounts = [$this->account()];
            }

            foreach ($accounts as $account) {
                $form->addAccount($account);
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($form);
            $em->flush();
        }

        if ($module && $module->getRole() === 'profile') {
            try {
                $pdcService->updateColumns($this->account(), json_decode($form->getCustomColumns(), true));
            } catch (OptimisticLockException $e) {
            } catch (ORMException $e) {
            }
        }

        $programsService->updateFormPrograms($form, $formPrograms);

        $em = $this->getDoctrine()->getManager();

        if ($oldSchemaArr !== $newSchemaArr) {
            $em->refresh($form);

            try {
                $reportsLabelsService->updateLabelsInReportsForForm($form);
            } catch (Exception $e) {
                return $this->getResponse()->success(['message' => 'Form Edited, but something went wrong while updating reports.']);
            }
        }

        $sharedFieldsService->updateSharedFields($form, $sharedFields, $this->phpDateFormat($this->user()));

        $formsDataCount = count($em->getRepository('App:FormsData')->findBy(['form' => $form]));
        $sharedFieldsCount = count($sharedFields);

        return $this->getResponse()->success([
            'message'                  => 'Form saved!',
            'run_update_shared_fields' => json_encode($oldSharedFieldsArr) !== json_encode($sharedFields),
            'run_calculations'         => $this->checkIfFormNeedsRecalculation($oldSchemaArr, $newSchemaArr, $oldSharedFieldsArr, $sharedFields, $oldCalculations, $calculations),
            'forms_data_count'         => $formsDataCount,
            'shared_fields_count'      => $sharedFieldsCount
        ]);
    }

    public function updateSharedFieldsAction(SharedFieldsService $sharedFieldsService): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $formId = $this->getRequest()->param('id');
        $form = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($formId, $this->account(), $this->access());

        $formsDataCount = $this->getRequest()->param('formsDataCount');
        $chunk = $this->getRequest()->param('chunk', 0);
        $sharedFieldsCount = $this->getRequest()->param('shared_fields_count');

        $size = 5000 / ($sharedFieldsCount == 0 ? 1 : $sharedFieldsCount);

        $offset = $chunk * $size;
        $limit = $size - 1;

        $sharedFieldsService->updateSharedFieldsValuesForForm($form, $limit, $offset);

        if ($chunk * $size >= $formsDataCount) {
            $status = 'done';
        } else {
            $status = 'pending';
        }

        return $this->getResponse()->success([
            'status' => $status,
            'chunk'  => $chunk + 1,
            'total'  => $formsDataCount
        ]);
    }

    public function calculateAction(BatchCalculationsHelper $batchCalculationsHelper): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $formId = $this->getRequest()->param('id');
        $form = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($formId, $this->account(), $this->access());

        $batchCalculationsHelper->recalculateFormsDataForForm($form);

        return $this->getResponse()->success(['message' => 'Form calculated']);
    }

    public function deleteAction(EventDispatcherInterface $eventDispatcher): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['CASE_MANAGER']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $id = $this->getRequest()->param('id');

        if ($id === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_FORM_ID);
        }

        $form = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($id, $this->account(), $this->access());

        if ($form === null) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_FORM);
        }

        if ($form->getModule() && $form->getModule()->getGroup() === 'core' && $this->access() < Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $em = $this->getDoctrine()->getManager();

        $account = $this->account();

        if ($account->getAccountType() == AccountType::PARENT) {
            $formsData = $this->getDoctrine()->getRepository('App:FormsData')->findBy(['form' => $form]);
        } else {
            $formsData = $this->getDoctrine()->getRepository('App:FormsData')->findBy([
                'form'       => $form,
                'account_id' => $account
            ]);
        }

        if ($form->getModule() && $form->getModule()->getRole() === 'referral') {
            $referrals = $em->getRepository('App:Referral')->findBy([
                'formData' => $formsData
            ]);

            foreach ($referrals as $referral) {
                if ($referral->getEnrolledParticipant()) {
                    return $this->getResponse()->error(ExceptionMessage::UNABLE_DELETE_FORM);
                }
            }
        }

        $form->removeAccount($account);
        $em->flush();

        // Remove form data
        foreach ($formsData as $data) {
            foreach ($data->getValues() as $value) {
                $em->remove($value);
            }

            $em->flush();

            $participantUserId = $data->getElementId();
            $assignment = $data->getAssignment();

            $em->remove($data);
            $em->flush();

            $eventDispatcher->dispatch(
                new FormDataRemovedEvent($form, $account, $participantUserId, $assignment),
                FormDataRemovedEvent::class
            );
        }

        $accounts = $form->getAccounts();

        $em->refresh($form);

        if (!count($accounts) || $account->getAccountType() == AccountType::PARENT) {
            foreach ($form->getFormsHistory() as $history) {
                $em->remove($history);
            }
            $em->flush();

            $em->remove($form);
            $em->flush();
        }

        return $this->getResponse()->success(['Form removed']);
    }


    /**
     * Block form, prevent editing
     */
    public function toggleBlockAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $id = $this->getRequest()->param('id');

        if ($id === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_FORM_ID);
        }

        $form = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($id, $this->account(), $this->access());

        if ($form === null) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_FORM);
        }

        if ($form->isEnabled()) {
            $form->setStatus(false);
        } else {
            $form->setStatus(true);
        }

        $em = $this->getDoctrine()->getManager();

        $em->persist($form);
        $em->flush();

        return $this->getResponse()->success(['Form saved']);
    }


    /**
     * Publish/unpublish action for form builder
     */
    public function togglePublishAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $em = $this->getDoctrine()->getManager();
        $id = $this->getRequest()->param('id');

        if ($id === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_FORM_ID);
        }

        $form = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($id, $this->account(), $this->access());

        if ($form === null) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_FORM);
        }

        if ($form->getPublish()) {
            $form->setPublish(false);
            $form->setStatus(true);
        } else {
            $form->setPublish(true);
            $form->setStatus(false);
        }

        $em->persist($form);
        $em->flush();

        return $this->getResponse()->success(['message' => 'Form saved', 'publish' => $form->getPublish()]);
    }

    protected function checkIfFormNeedsRecalculation(array $oldSchemaArr, array $newSchemaArr, array $oldSharedFieldsArr, array $newSharedFieldsArr, string $oldCalculations, string $newCalculations): bool
    {
        if ($newCalculations !== $oldCalculations) {
            return true;
        }

        $oldCalculationsArr = json_decode($oldCalculations, true);

        if (!count($oldCalculationsArr)) {
            return false;
        }

        $oldCalculationFields = [];

        foreach ($oldCalculationsArr as $calculation) {
            $oldCalculationFields = array_merge($oldCalculationFields, array_column($calculation['questions'], 'field'));
        }

        $oldFlatSchema = FormSchemaHelper::flattenColumns($oldSchemaArr);
        $newFlatSchema = FormSchemaHelper::flattenColumns($newSchemaArr);

        $fieldsNotPresentInNewSchema = array_diff(array_column($oldFlatSchema, 'name'), array_column($newFlatSchema, 'name'));
        $missingFieldsForOldCalculations = array_intersect($oldCalculationFields, $fieldsNotPresentInNewSchema);

        if (count($missingFieldsForOldCalculations)) {
            return true;
        }

        if ($this->checkIfValuesForMultipleValuesFieldsChanged($oldFlatSchema, $newFlatSchema)) {
            return true;
        }

        if ($this->checkSharedFieldsValues($oldCalculationFields, $oldSharedFieldsArr, $newSharedFieldsArr)) {
            return true;
        }

        return false;
    }

    private function checkIfValuesForMultipleValuesFieldsChanged(array $oldSchemaArr, array $newSchemaArr): bool
    {
        foreach ($oldSchemaArr as $oldField) {
            if (in_array($oldField['type'], ['checkbox-group', 'radio-group', 'select']) && is_array($oldField['values'])) {

                $oldValues = json_encode($oldField['values']);

                foreach ($newSchemaArr as $newField) {
                    if ($newField['name'] === $oldField['name']) {
                        $newValues = json_encode($newField['values']);

                        if ($oldValues !== $newValues) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    private function checkSharedFieldsValues(array $oldCalculationsFields, array $oldSharedFieldsArr, array $newSharedFieldsArr): bool
    {
        foreach ($oldSharedFieldsArr as $oldSharedFieldName => $oldSharedField) {
            if (!in_array($oldSharedFieldName, $oldCalculationsFields)) {
                continue;
            }

            if (!isset($newSharedFieldsArr[$oldSharedFieldName])) {
                continue;
            }

            if (json_encode($oldSharedField) !== json_encode($newSharedFieldsArr[$oldSharedFieldName])) {
                return true;
            }
        }

        return false;
    }

}
