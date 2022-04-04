<?php

namespace App\Service;

use App\Entity\Accounts;
use App\Entity\Forms;
use App\Enum\FormType;
use App\Enum\ParticipantType;
use Doctrine\ORM\EntityManagerInterface;

class AccountFormsService
{
    private EntityManagerInterface $em;

    protected $account;
    protected $modulesConfig;
    protected $accessLevel = 0;

    public function __construct(EntityManagerInterface $em, $modulesConfig)
    {
        $this->em = $em;
        $this->modulesConfig = $modulesConfig;
    }

    public function setAccount(Accounts $account)
    {
        $this->account = $account;
    }

    public function setAccessLevel($accessLevel)
    {
        $this->accessLevel = $accessLevel;
    }

    public function getSystemForms()
    {
        $participantType = $this->account->getParticipantType();

        if ($participantType == ParticipantType::INDIVIDUAL) {
            $formsKeys = $this->modulesConfig['participant_forms']['core'];
        }

        if ($participantType == ParticipantType::MEMBER) {
            $formsKeys = $this->modulesConfig['member_forms']['core'];
        }

        $modules = $this->em->getRepository('App:Modules')->findBy(['key' => $formsKeys]);
        $forms = $this->em->getRepository('App:Forms')->findForAccountAndModules($this->account, $modules);

        $data = [];

        foreach ($forms as $item) {
            $data[$item->getModule()->getRole()] = $this->formatFormListRow($item);
        }

        $modulesList = $this->formatModulesList($modules);

        return [
            'system_forms'         => $data,
            'system_forms_modules' => $modulesList
        ];
    }

    public function getParticipantForms()
    {
        $modules = $this->em->getRepository('App:Modules')->findBy(['key' => ['activities_services', 'assessment_outcomes']]);
        $forms = $this->em->getRepository('App:Forms')->findForAccountAndModules($this->account, $modules);

        $formsList = [];

        foreach ($forms as $item) {
            $formsList[$item->getId()] = $this->formatFormListRow($item);
        }

        $modulesList = $this->formatModulesList($modules);

        return [
            'participant_forms'         => $formsList,
            'participant_forms_modules' => $modulesList
        ];
    }

    public function getOrganizationForms()
    {
        $modules = $this->em->getRepository('App:Modules')->findBy(['group' => 'organization']);
        $forms = $this->em->getRepository('App:Forms')->findForAccountAndModules($this->account, $modules);

        $formsList = [];

        foreach ($forms as $item) {
            $formsList[$item->getId()] = $this->formatFormListRow($item);
        }

        $modulesList = $this->formatModulesList($modules);

        return [
            'organization_forms'         => $formsList,
            'organization_forms_modules' => $modulesList
        ];
    }

    public function getReferralForms()
    {
        $participantType = $this->account->getParticipantType();

        if ($participantType == ParticipantType::INDIVIDUAL) {
            $moduleKey = 'individuals_referral';
        }

        if ($participantType == ParticipantType::MEMBER) {
            $moduleKey = 'members_referral';
        }

        $module = $this->em->getRepository('App:Modules')->findBy(['key' => $moduleKey]);
        $forms = $this->em->getRepository('App:Forms')->findForAccountAndModules($this->account, $module);

        $formsList = [];

        foreach ($forms as $item) {
            $formsList[$item->getId()] = $this->formatFormListRow($item);
        }

        return [
            'referral_forms_module' => $this->formatModulesList($module)[0],
            'referral_forms'        => $formsList
        ];
    }

    public function getTemplates()
    {
        $templates = $this->em->getRepository('App:Forms')->findTemplatesForAccountsAndAccessLevel($this->account, $this->accessLevel);

        $templatesList = [];

        foreach ($templates as $template) {
            $templatesList[] = $this->formatFormListRow($template);
        }

        return [
            'forms_templates' => $templatesList
        ];
    }

    public function getProfileForm(): ?Forms
    {
        $participantType = $this->account->getParticipantType();

        if ($participantType == ParticipantType::INDIVIDUAL) {
            $module = $this->em->getRepository('App:Modules')->findBy(['key' => 'participants_profile']);
        }

        if ($participantType == ParticipantType::MEMBER) {
            $module = $this->em->getRepository('App:Modules')->findBy(['key' => 'members_profile']);
        }

        $forms = $this->em->getRepository('App:Forms')->findForAccountAndModules($this->account, [$module]);

        if (!count($forms)) {
            return null;
        }

        return end($forms);
    }

    public function addFormToChildAccounts(Forms $form, Accounts $parentAccount)
    {
        if ($form->getType() !== FormType::FORM) {
            return null;
        }

        if (!$parentAccount->getChildrenAccounts()) {
            return [];
        }

        $participantType = $parentAccount->getParticipantType();

        $config = $this->modulesConfig;

        if ($participantType == ParticipantType::INDIVIDUAL) {
            $config = $config['participant_forms'];
        }

        if ($participantType == ParticipantType::MEMBER) {
            $config = $config['member_forms'];
        }

        if (in_array($form->getModule()->getKey(), $config['core'])) {
            $accounts = $parentAccount->getChildrenAccounts();

            foreach ($accounts as $account) {
                $form->addAccount($account);
            }

            $this->em->persist($form);
            $this->em->flush();
        }
    }


    protected function formatFormListRow(Forms $item)
    {
        return [
            'id'                     => $item->getId(),
            'name'                   => $item->getName(),
            'description'            => $item->getDescription(),
            'data'                   => $item->getData(),
            'type'                   => $item->getType(),
            'user'                   => $item->getUser()->getData()->getFullName(),
            'created_date'           => $item->getCreatedDate(),
            'last_action_user'       => $item->getLastActionUser() === null ? '' : $item->getLastActionUser()->getData()->getFullName(),
            'last_modification_date' => $item->getLastActionDate() === null ? '' : $item->getLastActionDate()->format('Y-m-d H:i:s'),
            'status'                 => $item->getStatus(),
            'publish'                => $item->getPublish() ? 'Published' : 'Unpublished',
            'conditionals'           => $item->getConditionals(),
            'calculations'           => $item->getCalculations(),
            'hide_values'            => $item->getHideValues(),
            'extra_validation_rules' => $item->getExtraValidationRules() ?: "[]",
            'system_conditionals'    => $item->getSystemConditionals(),
            'columns_map'            => $item->getColumnsMap(),
            'module_key'             => $item->getModule() ? $item->getModule()->getKey() : null,
            'module_name'            => $item->getModule() ? $item->getModule()->getName() : '',
            'is_core'                => $item->getModule() ? ($item->getModule()->getGroup() == 'core') : false,
            'programs_str'           => $item->getPrograms() ? $this->getProgramsStr($item->getPrograms()) : '',
        ];
    }

    protected function formatModulesList($modules)
    {
        $list = [];

        foreach ($modules as $module) {
            $list[] = [
                'id'    => $module->getId(),
                'key'   => $module->getKey(),
                'group' => $module->getGroup(),
                'name'  => $module->getName(),
                'role'  => $module->getRole()
            ];
        }

        return $list;
    }

    protected function getProgramsStr($programs)
    {
        $programStr = '';
        foreach ($programs as $program) {
            $programStr .= $program->getName() . ', ';
        }
        return rtrim($programStr, ', ');
    }
}
