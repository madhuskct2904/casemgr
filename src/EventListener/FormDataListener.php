<?php

namespace App\EventListener;

use App\Domain\Form\BatchCalculationsHelper;
use App\Domain\Form\SharedFieldsService;
use App\Entity\Accounts;
use App\Entity\Assignments;
use App\Entity\Forms;
use App\Event\FormDataRemovedEvent;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class FormListener
 * @package App\Event
 */
class FormDataListener
{
    protected $em;
    protected $sharedFieldsService;
    protected $calculationsHelper;

    public function __construct(
        EntityManagerInterface $em,
        SharedFieldsService $sharedFieldsService,
        BatchCalculationsHelper $calculationsHelper
    )
    {
        $this->em = $em;
        $this->sharedFieldsService = $sharedFieldsService;
        $this->calculationsHelper = $calculationsHelper;
    }

    public function onFormDataRemoved(FormDataRemovedEvent $event)
    {
        $this->em->getRepository('App:ReportsForms')->removeForm($event->getForm());
        $this->updateSharedFields($event->getForm(), $event->getAccount(), $event->getParticipantUserId(), $event->getAssignment());
    }

    private function updateSharedFields(Forms $form, Accounts $account, ?int $participantUserId = null, ?Assignments $assignment)
    {
        if (!$form->hasSharedFields()) {
            return;
        }

        $sharedFields = $this->em->getRepository('App:SharedField')->findBy(['sourceForm' => $form]);

        $allDataIds = [];

        foreach ($sharedFields as $sharedField) {
            $value = $this->sharedFieldsService->getSharedFieldValue($sharedField, $account, $participantUserId, $assignment ? $assignment->getId() : null);

            $this->em->getRepository('App:ReportsForms')->invalidateForm($sharedField->getForm());

            $dataIds = $this->em->getRepository('App:FormsData')->findIdsForFormAccountParticipantUserIdAssignment($sharedField->getForm(), $account, $participantUserId, $assignment);
            $this->em->getRepository('App:FormsValues')->updateByNameAndDataIds($value, $sharedField->getFieldName(), $dataIds);

            $allDataIds = array_merge($allDataIds, $dataIds);
        }

        $formsData = $this->em->getRepository('App:FormsData')->findBy(['id' => array_unique($allDataIds)]);
        $this->calculationsHelper->recalculateFormsData($formsData);
    }
}
