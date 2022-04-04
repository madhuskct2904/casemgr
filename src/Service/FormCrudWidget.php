<?php

namespace App\Service;

use App\Entity\Accounts;
use App\Enum\AccountType;
use App\Enum\ParticipantStatus;
use App\Enum\ParticipantType;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final class FormCrudWidget
{
    private $em;
    private $account;
    private $module;
    private $participant;
    private $assignmentId;
    private $formsGroup;
    private $accessLevel;
    private $accountType;
    private $index = [];
    private $forceReadOnly = false;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function setup(Accounts $accounts, string $moduleKey, ?int $participantId, ?int $assignmentId, ?int $accessLevel): void
    {
        $this->account = $accounts;
        $module = $this->em->getRepository('App:Modules')->findOneBy(['key' => $moduleKey]);

        if (!$module) {
            throw new RuntimeException('Invalid module!');
        }

        $this->module = $module;
        $this->accountType = $accounts->getAccountType();
        $formsGroup = $module->getGroup();

        if ($formsGroup === 'organization' && !$accessLevel) {
            throw new RuntimeException('Missing access level!');
        }

        $this->formsGroup = $formsGroup;
        $this->accessLevel = $accessLevel;

        if ($formsGroup !== 'organization') {

            if (!$participantId) {
                throw new RuntimeException('Missing participant id!');
            }

            $this->participant = $this->em->getRepository('App:Users')->find($participantId);

            if (!$this->participant) {
                throw new RuntimeException('Invalid participant!');
            }

            if (!$assignmentId && ($this->participant->getData()->getStatus() !== ParticipantStatus::ACTIVE)) {
                $this->forceReadOnly = true;
            }
        }

        $this->assignmentId = $assignmentId;
    }

    public function getIndex(): array
    {
        $allForms = $this->getForms();
        $formsDataCriteria = $this->getFormsDataCriteria();

        switch ($this->accountType) {
            case AccountType::CHILD:

                $childForms = new ArrayCollection($this->em->getRepository('App:Forms')->findByModuleAndAccount($this->module, $this->account, false, false));

                foreach ($allForms as $form) {
                    $formsDataCriteria['form'] = $form;

                    $formsData = $this->em->getRepository('App:FormsData')->findBy(
                        $formsDataCriteria,
                        ['created_date' => 'DESC']
                    );

                    $formDataArr = $this->formDataToArray($formsData);

                    if (!$formsData && (!$childForms->contains($form) || !$form->getPublish())) {
                        continue;
                    }

                    if (!$childForms->contains($form) && count($formDataArr)) {
                        $this->addFormToIndex($form, $formDataArr, true);
                        continue;
                    }

                    if ($childForms->contains($form)) {
                        $this->addFormToIndex($form, $formDataArr, !$form->getPublish());
                    }
                }

                break;
            case AccountType::PROGRAM:
                foreach ($allForms as $form) {

                    $formsDataCriteria['form'] = $form;

                    $formsData = $this->em->getRepository('App:FormsData')->findBy(
                        $formsDataCriteria,
                        ['created_date' => 'DESC']
                    );

                    $formDataArr = $this->formDataToArray($formsData);

                    if ($this->participant) {
                        $formPrograms = $form->getPrograms();
                        $programsArr = [];

                        foreach ($formPrograms as $program) {
                            $programsArr[] = $program->getId();
                        }

                        if ($this->account->getParticipantType() == ParticipantType::INDIVIDUAL) {
                            $profileModuleKey = 'participants_profile';
                        }

                        if ($this->account->getParticipantType() == ParticipantType::MEMBER) {
                            $profileModuleKey = 'members_profile';
                        }

                        $commonPrograms = false;
                        $profile = $this->em->getRepository('App:Users')->getProfileData($this->participant, ['programs'], $profileModuleKey);
                        $participantPrograms = $profile['programs'] ?? [];

                        foreach ($participantPrograms as $participantProgram) {
                            if (in_array((int)$participantProgram, $programsArr)) {
                                $commonPrograms = true;
                                break;
                            }
                        }

                        if (!$commonPrograms && !$formsData) {
                            continue;
                        }

                        if (!$commonPrograms && $formsData) {
                            $this->addFormToIndex($form, $formDataArr, true);
                            continue;
                        }
                    }

                    if (!$formsData && !$form->getPublish()) {
                        continue;
                    }

                    $this->addFormToIndex($form, $formDataArr, !$form->getPublish());

                }
                break;
            default:
                foreach ($allForms as $form) {
                    $formsDataCriteria['form'] = $form;

                    $formsData = $this->em->getRepository('App:FormsData')->findBy(
                        $formsDataCriteria,
                        ['created_date' => 'DESC']
                    );

                    $formDataArr = $this->formDataToArray($formsData);

                    if (!$formsData && !$form->getPublish()) {
                        continue;
                    }

                    $this->addFormToIndex($form, $formDataArr, !$form->getPublish());
                }
        }

        return $this->index;

    }

    private function getForms()
    {
        $searchFormsInAccounts = [$this->account];

        if ($this->accountType === AccountType::CHILD) {
            $searchFormsInAccounts[] = $this->account->getParentAccount();
        }

        return $this->em->getRepository('App:Forms')->findByModuleAccountsAccessLevel($this->module, $searchFormsInAccounts, $this->accessLevel);
    }

    private function getFormsDataCriteria(): array
    {
        if ($this->formsGroup === 'organization') {
            return [
                'module'     => $this->module,
                'account_id' => $this->account->getId(),
            ];
        }

        return [
            'module'     => $this->module,
            'element_id' => $this->participant->getId(),
            'assignment' => $this->assignmentId
        ];

    }

    private function addFormToIndex($form, array $formData, bool $readOnly): void
    {
        $data = [
            'id'                     => $form->getId(),
            'name'                   => $form->getName(),
            'publish'                => $form->getPublish(),
            'multiple_entries'       => $form->getMultipleEntries(),
            'data'                   => $formData,
            'read_only'              => $this->forceReadOnly ? true : $readOnly,
            'share_with_participant' => $form->isSharedWithParticipant(),
            'captcha_enabled'        => $form->isCaptchaEnabled()
        ];

        if ($form->getPrograms()) {
            $data['programs'] = [];
            foreach ($form->getPrograms() as $program) {
                $data['programs'][] = $program->getId();
            }
        }

        $this->index[] = $data;
    }

    protected function formDataToArray(array $getData): array
    {
        $formData = [];

        foreach ($getData as $getDataRow) {
            $formData[] = [
                'id'                            => $getDataRow->getId(),
                'creator'                       => $getDataRow->getCreator() ? $getDataRow->getCreator()->getData()->getFullName() : 'System Administrator',
                'created_date'                  => $getDataRow->getCreatedDate(),
                'editor'                        => $getDataRow->getEditor() ? $getDataRow->getEditor()->getData()->getFullName() : 'System Administrator',
                'updated_date'                  => $getDataRow->getUpdatedDate(),
                'case_manager'                  => $getDataRow->getManager() ? $getDataRow->getManager()->getData()->getFullName() : null,
                'share_with_participant_status' => $getDataRow->getSharedForm() ? $getDataRow->getSharedForm()->getStatus() : null,
            ];
        }

        return $formData;
    }

}
