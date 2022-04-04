<?php

namespace App\Handler\Modules;

use App\Entity\Assignments;
use App\Entity\Forms;
use App\Entity\FormsData;
use App\Entity\SystemMessage;
use App\Entity\Users;
use App\Enum\ParticipantStatus;
use App\Enum\SystemMessageStatus;
use App\Event\FormDataRemovedEvent;
use App\Event\FormCreatedEvent;
use App\Event\FormUpdatedEvent;
use App\Handler\Modules\Handler\ModuleHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;

class ParticipantsAssignmentHandler extends ModuleHandler
{
    private $assignment;
    private $dismissedToActive = false;
    private $participant;
    private $formData;
    private $oldParticipantStatus;
    private $newProgramStatus;
    private $newParticipantStatus;
    private $isHistory = false;
    private $assignmentFormService;

    public function __construct(EntityManagerInterface $doctrine) {
        $this->setDoctrine($doctrine);
    }

    public function validate(): ?array
    {
        $data = $this->params();
        $map = $this->map();
        $errors = [];

        $this->assignmentFormService = $this->getContainer()->get('App\Service\AssignmentFormsService');
        $this->assignmentFormService->setForm($this->getForm());
        $programStatusLabel = $this->getNewProgramStatusLabel();
        $participantStatus = $this->assignmentFormService->getParticipantStatusByProgramStatus($programStatusLabel);

        $isHistory = $this->checkIfIsHistorical();

        if (!(bool)strtotime($data->get('program_status_start_date'))) {
            $errors[$map['program_status_start_date']] = 'Program Status Start Date is required.';
        }

        if (!$isHistory && (bool)strtotime($data->get('program_status_start_date'))) {
            $date = new \DateTime($data->get('program_status_start_date'));
            $date->setTime(0, 0, 0);
            $maxProgramEndDate = $this->getMaxProgramEndDate();

            if ($maxProgramEndDate && ($date->diff($maxProgramEndDate)->format('%r%a') > 0)) {
                $errors[$map['program_status_start_date']] = 'Program Start Date must equal to or greater than Historical Assignment Program End Date.';
            }
        }

        if (($participantStatus === ParticipantStatus::DISMISSED) && !(bool)strtotime($data->get('program_status_end_date'))) {
            $errors[$map['program_status_end_date']] = 'Program Status End Date is required.';
        }

        if ((bool)strtotime($data->get('program_status_end_date'))) {
            $now = new \DateTime();
            $date = new \DateTime($data->get('program_status_end_date'));

            $now->setTime(0, 0, 0);
            $date->setTime(0, 0, 0);

            if ($date > $now) {
                $errors[$map['program_status_end_date']] = 'Future Date is not allowed.';
            }

            if ($date <= $now && $participantStatus !== ParticipantStatus::DISMISSED && !$isHistory) {
                $errors[$map['program_status_end_date']] = 'To close the case, you must select a valid Dismissal Program Status.';
            }

            $startDate = new \DateTime($data->get('program_status_start_date'));
            $startDate->setTime(0, 0, 0);

            if (($date < $startDate) && $participantStatus === ParticipantStatus::DISMISSED) {
                $errors[$map['program_status_end_date']] = 'Program Status End Date must equal to or greater than Program Start Date.';
            }
        }

        return count($errors) ? $errors : null;
    }


    public function before($access = 0)
    {
        if ($access < Users::ACCESS_LEVELS['CASE_MANAGER']) {
            throw new \Exception('No access.');
        }

        $this->participant = $this->getDoctrine()->getRepository('App:Users')->findOneBy(['id' => $this->getElementId()]);

        if (!$this->participant) {
            throw new \Exception('Invalid participant.');
        }

        $this->oldParticipantStatus = $this->participant->getData()->getStatus();

        $em = $this->getDoctrine();
        $data = $this->params();

        $this->newProgramStatus = $this->getNewProgramStatusLabel();
        $this->newParticipantStatus = $this->assignmentFormService->getParticipantStatusByProgramStatus($this->newProgramStatus);

        $this->isHistory = $this->checkIfIsHistorical();
        $this->dismissedToActive = $this->newParticipantStatus === ParticipantStatus::ACTIVE && $this->isHistory;

        if ($this->dismissedToActive && $access < Users::ACCESS_LEVELS['SUPERVISOR']) {
            throw new \Exception('No access.');
        }

        $module = $em->getRepository('App:Modules')->findOneBy([
            'key' => 'participants_assignment'
        ]);

        if ($this->getDataId() !== null) {
            $this->formData = $this->getDoctrine()->getRepository('App:FormsData')->findOneBy(['id' => $this->getDataId()]);
            $form = $this->formData->getForm();

            if (!$this->isItMostRecentDismissedAssignment($this->getDataId(), $module, $form, $this->participant->getId()) && $this->dismissedToActive) {
                throw new \Exception('Security violation! Trying to change dismissed to active status for not the latest assignment!');
            }
        }

        $assignment = null;

        if ($this->formData) {
            $assignment = $this->formData->getAssignment();
        }

        $manager = $this->getDoctrine()->getRepository('App:Users')->findOneBy(['id' => $data->get('primary_case_manager_id')]);
        $manager2 = $this->getDoctrine()->getRepository('App:Users')->findOneBy(['id' => $data->get('secondary_case_manager_id')]);

        if (!$assignment && $this->newParticipantStatus === ParticipantStatus::DISMISSED) {
            $this->participant->getData()->setCaseManager('');
            $this->participant->getData()->setCaseManagerSecondary(null);
        }

        if ($this->newParticipantStatus === ParticipantStatus::ACTIVE) {
            $this->updateAssignedManagers($manager, $manager2);
        }

        $this->participant->getData()->setStatusLabel($this->newProgramStatus ?: '');
        $this->participant->getData()->setStatus($this->newParticipantStatus);
        $em->flush();

        // UPDATE ASSIGNMENT - EDITING HISTORICAL ENTRY
        if ($assignment) {
            $assignment = $this->setAssignmentData($assignment, $manager);
            $em->flush();
            $this->assignment = $assignment;
            $this->response('assignment_status', 'updated');
            return;
        }

        // CREATE ASSIGNMENT - IF THERE IS NO ASSIGNMENT AND PARTICIPANT IS DISMISSED
        if (!$assignment && $this->newParticipantStatus === ParticipantStatus::DISMISSED) {
            $assignment = new Assignments();
            $assignment = $this->setAssignmentData($assignment, $manager);
            $em->persist($assignment);
            $em->flush();

            // clone avatar
            $fs = new Filesystem();
            $path = $this->getContainer()->getParameter('avatar_directory') . '/';
            $avatar = $this->participant->getData()->getAvatar();
            $avatarCopy = $assignment->getId() . '-' . $avatar;

            if ($avatar && $fs->exists($path . $avatar)) {
                $fs->copy($path . $avatar, $path . $avatarCopy);
                $assignment->setAvatar($avatarCopy);
                $em->flush();
            }

            $this->assignment = $assignment;
            $this->response('message', 'Case dismissed.');
            $this->response('assignment_status', 'dismissed');
            return null;
        }

        if ($this->oldParticipantStatus === ParticipantStatus::ACTIVE && $this->newParticipantStatus === ParticipantStatus::ACTIVE) {
            $this->response('assignment_status', 'updated');
            return null;
        }

        $this->response('message', 'New assignment added.');
        $this->response('assignment_status', 'created');
        return null;
    }

    public function after()
    {
        if ($this->dismissedToActive) {
            $this->dismissedToActive();
            return;
        }

        if ($this->assignment && !$this->isHistory) {
            $this->cloneProfileAndContactForms();
            $this->assignAssignmentToCaseNotes();
            $this->assignAssignmentToMessages();
        }
    }

    private function isItMostRecentDismissedAssignment($dataId, $module, Forms $form, $elementId): bool
    {
        $em = $this->getDoctrine();

        $filledForms = $em->getRepository('App:FormsData')->findBy([
            'module'     => $module,
            'form'       => $form,
            'element_id' => $elementId
        ], ['id' => 'DESC']);

        foreach ($filledForms as $filledForm) {
            if ($filledForm->getAssignment()) {
                return $dataId == $filledForm->getId();
            }
        }

        return false;
    }

    private function removeCurrentParticipantForms($userId): void
    {
        $em = $this->getDoctrine();
        $userForms = $this->getDoctrine()->getRepository('App:FormsData')->findBy([
            'element_id' => $userId,
            'assignment' => null
        ]);

        // find current participants profile and contact and remove them
        foreach ($userForms as $userForm) {
            $form = $userForm->getForm();
            $account = $userForm->getAccount();

            $em->remove($userForm);

            $eventDispatcher = $this->getContainer()->get('App\Service\EventDispatcherFactoryService')->getEventDispatcher();
            $eventDispatcher->dispatch(
                new FormDataRemovedEvent($form, $account, $userId, null),
                FormDataRemovedEvent::class
            );
        }


        $em->flush();
    }

    private function setAssignmentFormsAsCurrent($assignment): void
    {
        $em = $this->getDoctrine();
        $attachedForms = $this->getDoctrine()->getRepository('App:FormsData')->findBy([
            'assignment' => $assignment
        ]);

        foreach ($attachedForms as $attachedForm) {

            $moduleKey = $attachedForm->getModule() ? $attachedForm->getModule()->getKey() : null;
            $attachedForm->setAssignment(null);
            $em->persist($attachedForm);

            if ($moduleKey && $this->getContainer()->has('app.forms.values_handler.'.$moduleKey)) {
                $valuesHandler = $this->getContainer()->get('app.forms.values_handler.'.$moduleKey);
                $valuesHandler->setFormData($attachedForm);
                $valuesHandler->handle();
            }

            $eventDispatcher = $this->getContainer()->get('App\Service\EventDispatcherFactoryService')->getEventDispatcher();
            $eventDispatcher->dispatch(new FormUpdatedEvent($attachedForm), FormUpdatedEvent::class);
        }

        $em->flush();
    }

    private function removeAssignmentFromFormData(FormsData $formsData): void
    {
        $em = $this->getDoctrine();
        $formsData->setAssignment(null);
        $em->persist($formsData);
        $em->flush();
        $eventDispatcher = $this->getContainer()->get('App\Service\EventDispatcherFactoryService')->getEventDispatcher();
        $eventDispatcher->dispatch(new FormUpdatedEvent($formsData), FormUpdatedEvent::class);
    }

    private function removeCurrentUserCaseNotes($userId): void
    {
        $em = $this->getDoctrine();

        $userCaseNotes = $this->getDoctrine()->getRepository('App:CaseNotes')->findBy([
            'participant' => $userId,
            'assignment'  => null
        ]);

        foreach ($userCaseNotes as $userCaseNote) {
            $em->remove($userCaseNote);
        }

        $em->flush();
    }

    private function setAssignmentCaseNotesAsCurrent(Assignments $assignment): void
    {
        $em = $this->getDoctrine();

        $userCaseNotes = $this->getDoctrine()->getRepository('App:CaseNotes')->findBy([
            'assignment' => $assignment
        ]);

        foreach ($userCaseNotes as $userCaseNote) {
            $userCaseNote->setAssignment(null);
            $em->persist($userCaseNote);
        }

        $em->flush();
    }

    private function setSMSMessagesAsCurrent(Assignments $assignment): void
    {
        $em = $this->getDoctrine();

        $SMSMessages = $this->getDoctrine()->getRepository('App:Messages')->findBy([
            'assignment' => $assignment
        ]);

        foreach ($SMSMessages as $message) {
            $message->setAssignment(null);
            $em->persist($message);
        }

        $em->flush();
    }

    private function removeAssignment(Assignments $assignment): void
    {
        $assignmentId = $assignment->getId();
        $em = $this->getDoctrine();
        $em->remove($assignment);
        $em->flush();
        $this->response('assignment_removed', $assignmentId);
    }

    private function updateParticipantProgramStatus($programStatusLabel, $participantStatus): void
    {
        $em = $this->getDoctrine();
        $participantData = $this->participant->getData();
        $participantData->setStatusLabel($programStatusLabel);
        $participantData->setStatus($participantStatus);
        $em->persist($participantData);
        $em->flush();
    }

    private function checkIfIsHistorical(): bool
    {
        $isHistory = false;

        if ($this->getDataId() !== null) {
            $formData = $this->getDoctrine()->getRepository('App:FormsData')->findOneBy(['id' => $this->getDataId()]);
            $isHistory = $formData->getAssignment() ? true : false;
        }

        return $isHistory;
    }

    private function dismissedToActive(): void
    {
        if (!$this->assignment instanceof Assignments) {
            throw new \Exception('Invalid assignment.');
        }

        $this->updateParticipantProgramStatus($this->newProgramStatus, ParticipantStatus::ACTIVE);

        $formMap = array_flip(array_filter($this->map()));

        $em = $this->getDoctrine();

        foreach ($this->formData->getValues() as $formValue) {
            $formValueName = $formValue->getName();

            if (!isset($formMap[$formValueName])) {
                continue;
            }
            if ($formMap[$formValueName] === 'program_status') {
                $formValue->setValue($this->newProgramStatus);
            }

            if ($formMap[$formValueName] === 'program_status_end_date') {
                $formValue->setValue('');
            }
            $em->persist($formValue);
        }

        $em->flush();

        $this->removeCurrentParticipantForms($this->participant->getId());
        $this->setAssignmentFormsAsCurrent($this->assignment);
        $this->removeAssignmentFromFormData($this->formData);
        $this->removeCurrentUserCaseNotes($this->participant->getId());
        $this->setAssignmentCaseNotesAsCurrent($this->assignment);
        $this->setSMSMessagesAsCurrent($this->assignment);
        $this->removeAssignment($this->assignment);
    }

    private function cloneFormData(FormsData $formData): void
    {
        $em = $this->getDoctrine();
        $eventDispatcher = $this->getContainer()->get('App\Service\EventDispatcherFactoryService')->getEventDispatcher();
        $newFormData = clone($formData);
        $newFormDataValues = clone($formData->getValues());
        $em->persist($newFormData);
        $em->flush();
        foreach ($newFormDataValues as $value) {
            $newFormDataValue = clone($value);
            $newFormDataValue->setData($newFormData);
            $em->persist($newFormDataValue);
            $newFormData->addValue($newFormDataValue);
        }
        $em->flush();
        $eventDispatcher->dispatch(new FormCreatedEvent($newFormData), FormCreatedEvent::class);
    }

    private function cloneProfileAndContactForms(): void
    {
        $em = $this->getDoctrine();

        // assign Assignment ID to forms_data (all modules) of this participant
        $formsData = $em->createQueryBuilder()
            ->select(['fd'])
            ->from('App:FormsData', 'fd')
            ->where('fd.module IN(:ids)')
            ->setParameter('ids', [1, 2, 3, 4, 5, 8])
            ->andWhere('fd.element_id = :element_id')
            ->setParameter('element_id', (string)$this->assignment->getParticipant()->getId())
            ->andWhere('fd.assignment IS NULL')
            ->getQuery()
            ->getResult();

        $eventDispatcher = $this->getContainer()->get('App\Service\EventDispatcherFactoryService')->getEventDispatcher();

        // clone Profile & Contact
        foreach ($formsData as $formData) {

            if (in_array($formData->getModule()->getKey(), ['participants_profile', 'participants_contact', 'members_profile'])) {
                $this->cloneFormData($formData);
            }

            $formData->setAssignment($this->assignment);
            $em->flush();
            $eventDispatcher->dispatch(new FormUpdatedEvent($formData), FormUpdatedEvent::class);
        }
    }

    private function assignAssignmentToCaseNotes(): void
    {
        $em = $this->getDoctrine();
        $caseNotes = $em->createQueryBuilder()
            ->select(['cn'])
            ->from('App:CaseNotes', 'cn')
            ->where('cn.participant = :element_id')
            ->setParameter('element_id', (string)$this->assignment->getParticipant()->getId())
            ->andWhere('cn.assignment IS NULL')
            ->getQuery()
            ->getResult();

        foreach ($caseNotes as $caseNote) {
            $caseNote->setAssignment($this->assignment);
        }
        $em->flush();
    }

    private function assignAssignmentToMessages(): void
    {
        $em = $this->getDoctrine();
        $messages = $em->createQueryBuilder()
            ->select(['m'])
            ->from('App:Messages', 'm')
            ->where('m.participant = :element_id')
            ->setParameter('element_id', (string)$this->assignment->getParticipant()->getId())
            ->andWhere('m.assignment IS NULL')
            ->getQuery()
            ->getResult();

        foreach ($messages as $message) {
            $message->setAssignment($this->assignment);
        }

        $em->flush();
    }

    private function setAssignmentData(Assignments $assignment, ?Users $manager): Assignments
    {
        $data = $this->params();

        $startDate = $data->get('program_status_start_date') ? new \DateTime($data->get('program_status_start_date')) : null;
        $endDate = $data->get('program_status_end_date') ? new \DateTime($data->get('program_status_end_date')) : null;

        if ($this->newProgramStatus === ParticipantStatus::DISMISSED && !$endDate) {
            $endDate = new \DateTime();
        }

        $assignment
            ->setProgramStatusStartDate($startDate)
            ->setProgramStatusEndDate($endDate)
            ->setProgramStatus($this->newProgramStatus)
            ->setPrimaryCaseManager($manager)
            ->setParticipant($this->participant);

        return $assignment;
    }

    private function getMaxProgramEndDate(): ?\DateTime
    {
        $participant = $this->getDoctrine()->getRepository('App:Users')->find($this->getElementId());

        $result = $this->getDoctrine()->getRepository('App:Assignments')->findMaxProgramEndDateForParticipant($participant);

        if (!$result) {
            return null;
        }

        return $result->getProgramStatusEndDate();
    }

    private function getNewProgramStatusLabel(): ?string
    {
        $statusField = $this->assignmentFormService->getProgramStatusFieldName();

        $programStatusLabel = null;

        foreach ($this->getParams() as $newData) {
            if ($newData['name'] == $statusField) {
                $programStatusLabel = $newData['value'];
                break;
            }
        }
        return $programStatusLabel;
    }

    /**
     * @param $manager
     * @param $manager2
     */
    private function updateAssignedManagers(?Users $manager, ?Users $manager2): void
    {
        $oldPrimaryManager = $this->participant->getData()->getCaseManager();
        $oldSecondaryManager = $this->participant->getData()->getCaseManagerSecondary();

        $title = $this->participant->getData()->getFullName();

        if ($manager && ((int)$oldPrimaryManager !== $manager->getId())) {
            $this->createManagerAlert($manager, $title);
        }

        if ($manager2 && ((int)$oldSecondaryManager !== $manager2->getId())) {
            $this->createManagerAlert($manager, $title);
        }

        $this->participant->getData()->setCaseManager($manager ? $manager->getId() : '');
        $this->participant->getData()->setCaseManagerSecondary($manager2 ? $manager2->getId() : null);
    }

    /**
     * @param Users $manager
     * @param $title
     * @return array
     */
    private function createManagerAlert(Users $manager, $title): SystemMessage
    {
        $systemMessage = new SystemMessage;
        $systemMessage->setUser($manager);
        $systemMessage->setTitle($title);
        $systemMessage->setRelatedTo('participant');
        $systemMessage->setRelatedToId($this->participant->getId());
        $systemMessage->setBody('Participant assigned to your caseload.');
        $systemMessage->setStatus(SystemMessageStatus::UNREAD);
        $systemMessage->setType('assigned_case_manager');
        $systemMessage->setCreatedAt(new \DateTime());
        $systemMessage->setAccount($this->getAccount());

        $em = $this->getDoctrine();
        $em->persist($systemMessage);
        $em->flush();

        return $systemMessage;
    }

}
