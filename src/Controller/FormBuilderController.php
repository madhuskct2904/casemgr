<?php

namespace App\Controller;

use App\Entity\Forms;
use App\Entity\Users;
use App\Enum\AccountType;
use App\Enum\FormType;
use App\Enum\ParticipantType;
use App\Exception\ExceptionMessage;
use App\Service\AccountFormsService;
use App\Domain\FormBuilder\FormBuilderHelper;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use function Sentry\captureException;

class FormBuilderController extends Controller
{
    public function getSystemFormsAction(AccountFormsService $accountsFormsService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $accountsFormsService->setAccount($this->account());

        try {
            $systemForms = $accountsFormsService->getSystemForms();
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry
            return $this->getResponse()->error(ExceptionMessage::DEFAULT, 500);
        }

        return $this->getResponse()->success($systemForms);
    }

    public function getParticipantFormsAction(AccountFormsService $accountsFormsService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $accountsFormsService->setAccount($this->account());

        try {
            $participantForms = $accountsFormsService->getParticipantForms();
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT, 500);
        }

        return $this->getResponse()->success($participantForms);
    }

    public function getOrganizationFormsAction(AccountFormsService $accountsFormsService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $accountsFormsService->setAccount($this->account());

        try {
            $organizationForms = $accountsFormsService->getOrganizationForms();
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT, 500);
        }

        return $this->getResponse()->success($organizationForms);
    }

    public function getFormsTemplatesAction(AccountFormsService $accountsFormsService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $accountsFormsService->setAccount($this->account());
        $accountsFormsService->setAccessLevel($this->access());

        try {
            $templates = $accountsFormsService->getTemplates();
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT, 500);
        }

        return $this->getResponse()->success($templates);
    }

    public function getReferralFormsAction(AccountFormsService $accountsFormsService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $accountsFormsService->setAccount($this->account());

        try {
            $referralForms = $accountsFormsService->getReferralForms();
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT, 500);
        }

        return $this->getResponse()->success($referralForms);

    }

    public function getFormsWithSharedFieldsAction(FormBuilderHelper $formBuilderHelper): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 403);
        }

        $formBuilderHelper->setAccount($this->account());

        $forms = $formBuilderHelper->getFormsWithSharedFields();

        return $this->getResponse()->success(['forms' => $forms]);
    }

    public function duplicateAction(AccountFormsService $accountsFormsService): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $id = $this->getRequest()->param('id');
        $name = $this->getRequest()->param('name');
        $description = $this->getRequest()->param('description');
        $type = $this->getRequest()->param('type');
        $moduleKey = $this->getRequest()->param('module');
        $accessLevel = $this->getRequest()->param('access_level', null);

        if ($id === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_FORM_ID);
        }

        if ($name === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_FORM_NAME);
        }

        if (!FormType::isValidValue($type)) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_FORM_TYPE);
        }

        $form = $this->getDoctrine()->getRepository('App:Forms')->findOneByIdAccountAccessLevel($id, $this->account(), $this->access());

        if ($form === null) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_FORM);
        }

        $newForm = new Forms();

        $newForm->setDescription($description);
        $newForm->setData($form->getData());
        $newForm->setCreatedDate(new \DateTime());
        $newForm->setName($name);
        $newForm->setStatus($form->getStatus());
        $newForm->setType($type);
        $newForm->setUser($this->user());
        $newForm->setConditionals($form->getConditionals());
        $newForm->setCalculations($form->getCalculations());
        $newForm->setSystemConditionals($form->getSystemConditionals());
        $newForm->setColumnsMap($form->getColumnsMap());
        $newForm->setPublish(false);
        $newForm->setAccessLevel($accessLevel ? Users::ACCESS_LEVELS[$accessLevel] : Users::ACCESS_LEVELS['VOLUNTEER']);
        $newForm->setMultipleEntries(true);
        $newForm->setHideValues($form->getHideValues());

        $account = $this->account();

        if (!($account->isMain() && $type == FormType::TEMPLATE)) {
            $newForm->addAccount($account);
        }

        if ($moduleKey !== null && $type == FormType::FORM) {
            $module = $this->getDoctrine()->getRepository('App:Modules')->findOneBy(['key' => $moduleKey]);

            if (!$form->getAccessLevel() && in_array($module->getKey(), ['activities_services', 'assessment_outcomes'])) {
                $newForm->setAccessLevel(Users::ACCESS_LEVELS['VOLUNTEER']);
            }

            if (in_array($module->getRole(), ['profile', 'contact', 'assignment'])) {

                $formExists = $this->getDoctrine()->getRepository('App:Forms')->findForAccountAndModules($account, [$module]);

                if ($formExists) {
                    return $this->getResponse()->error(ExceptionMessage::NOT_UNIQUE_FORM);
                }
            }

            $newForm->setModule($module);

            if ($module->getRole() === 'referral') {
                $newForm->setShareUid(bin2hex(openssl_random_pseudo_bytes(20)));
            }
        }

        $em = $this->getDoctrine()->getManager();

        $em->persist($newForm);
        $em->flush();

        if ($account->getAccountType() === AccountType::PARENT) {
            $accountFormsService->addFormToChildAccounts($newForm, $account);
        }

        return $this->getResponse()->success(['Form created', 'id' => $newForm->getId()]);
    }

    public function modulesIndexAction(): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->account()->getParticipantType() == ParticipantType::MEMBER) {
            $modulesKeys = $this->getParameter('modules')['member_forms'];
        }

        if ($this->account()->getParticipantType() == ParticipantType::INDIVIDUAL) {
            $modulesKeys = $this->getParameter('modules')['participant_forms'];
        }

        $modules = $this->getDoctrine()->getRepository('App:Modules')->findBy([
            'key' => array_merge($modulesKeys['core'], $modulesKeys['multiple'], $modulesKeys['organization'])
        ]);

        $data = [];

        foreach ($modules as $module) {
            $data[] = [
                'id'          => $module->getId(),
                'group'       => $module->getGroup(),
                'key'         => $module->getKey(),
                'name'        => $module->getName(),
                'role'        => $module->getRole(),
                'columns_map' => json_decode($module->getColumnsMap(), true)
            ];
        }

        return $this->getResponse()->success($data);
    }
}
