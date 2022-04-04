<?php

namespace App\Service;

use App\Domain\Form\FormSchemaHelper;
use App\Domain\SharedForms\SharedFormServiceException;
use App\Entity\Accounts;
use App\Entity\CaseNotes;
use App\Entity\Forms;
use App\Entity\FormsData;
use App\Entity\SharedForm;
use App\Entity\Users;
use App\Event\SharedFormSendingFailedEvent;
use App\Event\SharedFormSentEvent;
use App\Exception\SaveValuesServiceException;
use App\Service\Forms\SaveValuesService;
use App\Service\SharedFormMessageStrategy\SharedFormMessageChannelStrategyInterface;
use App\Service\SharedFormMessageStrategy\SharedFormMessageStatus;
use App\Utils\Helper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class SharedFormService
{
    private $em;
    private $eventDispatcher;
    private $sharedFormHelper;
    private $formHelper;
    private $saveValuesService;

    public function __construct(EntityManagerInterface $entityManager, EventDispatcherInterface $eventDispatcher, SharedFormHelper $sharedFormHelper, FormSchemaHelper $formHelper, SaveValuesService $saveValuesService)
    {
        $this->em = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->sharedFormHelper = $sharedFormHelper;
        $this->formHelper = $formHelper;
        $this->saveValuesService = $saveValuesService;
    }

    public function shareWithParticipant(int $formDataId, int $participantId, Accounts $account, Users $user, SharedFormMessageChannelStrategyInterface $communicationChannel): SharedForm
    {
        $formData = $this->em->getRepository('App:FormsData')->find($formDataId);

        if (!$formDataId) {
            throw new SharedFormServiceException('Invalid form data!');
        }

        $participant = $this->em->getRepository('App:Users')->find($participantId);

        if (!$participant) {
            throw new SharedFormServiceException('Invalid participant!');
        }

        $sharedFormEntry = $this->em->getRepository('App:SharedForm')->findOneBy([
            'formData'        => $formData,
            'participantUser' => $participant
        ]);

        if (!$sharedFormEntry) {
            $sharedFormEntry = $this->createSharedFormEntry($user, $account, $participant, $formData);
        }

        if ($sharedFormEntry->getStatus() === $sharedFormEntry::STATUS['COMPLETED']) {
            throw new SharedFormServiceException('Form can be completed only once.');
        }

        try {
            $communicationChannel->send($sharedFormEntry);
            $sharedFormEntry->setSentVia($communicationChannel->getStrategyName());
        } catch (\Exception $e) {
            $sharedFormEntry->setStatus(SharedForm::STATUS['FAILED']);
            $this->em->flush();
            $this->sharedFormHelper->addSystemMessage($sharedFormEntry);
            $message = $communicationChannel->getStatus()->getMessage();
            $this->sharedFormHelper->addFormSentCommunicationNote($sharedFormEntry, $message);
            $this->eventDispatcher->dispatch(
                new SharedFormSendingFailedEvent($sharedFormEntry),
                SharedFormSendingFailedEvent::class
            );
            throw new SharedFormServiceException($e->getMessage());
        }

        $sharedFormEntry->setStatus(SharedForm::STATUS['SENT']);
        $sharedFormEntry->setSentAt(new \DateTime());
        $this->em->flush();

        if ($communicationChannel->getStatus()->getStatus() === SharedFormMessageStatus::STATUS_SUCCESS) {
            $this->eventDispatcher->dispatch(new SharedFormSentEvent($sharedFormEntry), SharedFormSentEvent::class);
        }

        $message = $communicationChannel->getStatus()->getMessage();
        $this->sharedFormHelper->addFormSentCommunicationNote($sharedFormEntry, $message);

        return $sharedFormEntry;
    }

    public function submitForm(SharedForm $sharedForm, array $data, array $files): SharedForm
    {
        $form = $sharedForm->getFormData()->getForm();
        $fillableFields = $this->getFillableFields($form);

        foreach ($data as $idx => $submittedData) {

            if (strpos($submittedData['name'], 'checkbox-group-') !== false) {
                $name = substr($submittedData['name'], 0, strrpos($submittedData['name'], '-'));
            } else {
                $name = $submittedData['name'];
            }

            if (!in_array($name, $fillableFields)) {
                unset($data[$idx]);
            }
        }

        try {
            $this->saveValuesService->clearValues($sharedForm->getFormData(), $fillableFields);
            $this->saveValuesService->storeValues($sharedForm->getFormData(), $data, $files);
            $sharedForm->setSubmissionToken(bin2hex(openssl_random_pseudo_bytes(20)));
            $sharedForm->setStatus(SharedForm::STATUS['COMPLETED']);
            $this->addSubmissionCommunicationNote($sharedForm);
            $sharedForm->setCompletedAt(new \DateTime());
            $this->em->persist($sharedForm);
            $this->em->flush();
            $this->sharedFormHelper->addSystemMessage($sharedForm);
        } catch (SaveValuesServiceException $e) {
            throw new SharedFormServiceException($e->getMessage());
        }

        return $sharedForm;
    }

    public function getLockedFields(Forms $form)
    {
        $schema = $this->formHelper->setForm($form)->getFlattenColumns();
        $fields = [];

        foreach ($schema as $field) {
            if (isset($field['by_participant']) && $field['by_participant']) {
                continue;
            }
            $fields[] = $field['name'];
        }

        return $fields;
    }

    public function getFillableFields(Forms $form)
    {
        $schema = $this->formHelper->setForm($form)->getFlattenColumns();
        $fields = [];

        foreach ($schema as $field) {
            if (isset($field['by_participant']) && $field['by_participant']) {
                $fields[] = $field['name'];
            }
        }

        return $fields;
    }

    private function createSharedFormEntry(Users $user, Accounts $account, Users $participant, FormsData $formsData): SharedForm
    {
        do {
            $uid = Helper::generateRandomId();
        } while ($this->em->getRepository('App:SharedForm')->findOneBy(['uid' => $uid]));

        $sharedForm = new SharedForm();

        $sharedForm->setUser($user);
        $sharedForm->setFormData($formsData);
        $sharedForm->setAccount($account);
        $sharedForm->setParticipantUser($participant);
        $sharedForm->setStatus(SharedForm::STATUS['SENDING']);
        $sharedForm->setUid($uid);

        $this->em->persist($sharedForm);
        $this->em->flush();

        return $sharedForm;
    }

    private function addCaseNote(Users $user, Users $participantUser, string $noteTitle, string $type): CaseNotes
    {
        $caseNote = new CaseNotes();
        $caseNote->setCreatedBy($user);
        $caseNote->setCreatedAt(new \DateTime());
        $caseNote->setType($type);
        $caseNote->setNote($noteTitle);
        $caseNote->setParticipant($participantUser);
        $caseNote->setReadOnly(true);

        $this->em->persist($caseNote);
        $this->em->flush();

        return $caseNote;
    }

    private function addSubmissionCommunicationNote(SharedForm $sharedForm): void
    {
        $noteTitle = '<a href="' . $this->sharedFormHelper::generateInternalFormUrl($sharedForm) . '">' . $sharedForm->getFormData()->getForm()->getName() . '</a> completed.';
        $this->addCaseNote($sharedForm->getUser(), $sharedForm->getParticipantUser(), $noteTitle, 'form_completed');
    }

}
