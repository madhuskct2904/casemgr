<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Accounts;
use App\Entity\Forms;
use App\Enum\ParticipantStatus;
use App\Enum\ParticipantType;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Exception;

class AssignmentFormsService
{
    protected $em;
    protected $form;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function setForm(Forms $form)
    {
        $this->form = $form;
    }

    public function getProgramStatusFieldName(): ?string
    {
        if (!$this->form) {
            throw new Exception('Form not set!');
        }

        $systemConditionals = $this->form->getSystemConditionals() ? json_decode($this->form->getSystemConditionals(), true) : null;

        if (!$systemConditionals || !isset($systemConditionals['programStatus'])) {
            return null;
        }

        return $systemConditionals['programStatus']['field'];
    }

    public function getProgramStatusesForOrganization(Accounts $account): array
    {
        $module = $this->em->getRepository('App:Modules')->findOneBy(['key' => 'participants_assignment']);
        $assignmentForms = $this->em->getRepository('App:Forms')->findByModuleAndAccount($module, $account, false, true);

        $statuses = [];

        foreach ($assignmentForms as $assignmentForm) {
            if (!$assignmentForm->getSystemConditionals()) {
                continue;
            }

            $systemConditionals = json_decode($assignmentForm->getSystemConditionals(), true);

            if (json_last_error() !== JSON_ERROR_NONE
            || !isset($systemConditionals['programStatus'])
            || !isset($systemConditionals['programStatus']['conditions'])) {
                continue;
            }

            $statuses[$assignmentForm->getId()] = array_keys($systemConditionals['programStatus']['conditions']);
        }

        return $statuses;
    }

    public function getProgramStatusParticipantStatusMapForOrganization(Accounts $account): array
    {
        $statuses = $this->getProgramStatusesForOrganization($account);
        $map = [];

        foreach ($statuses as $formId => $formStatuses) {
            $form = $this->em->getRepository('App:Forms')->find($formId);
            $this->setForm($form);
            foreach ($formStatuses as $formStatus) {
                $map[$formStatus] = $this->getParticipantStatusByProgramStatus($formStatus);
            }
        }

        ksort($map, SORT_STRING | SORT_FLAG_CASE);

        return $map;
    }

    public function getParticipantStatusByProgramStatus(?string $programStatusLabel): ?int
    {
        $systemConditionals = $this->form->getSystemConditionals() ? json_decode($this->form->getSystemConditionals(), true) : null;

        if (!$systemConditionals) {
            return ParticipantStatus::NONE;
        }

        if (empty($programStatusLabel)) {
            return ParticipantStatus::NONE;
        }

        $status = $systemConditionals['programStatus']['conditions'][$programStatusLabel];

        if (!ParticipantStatus::isValidValue((int)$status)) {
            return ParticipantStatus::NONE;
        }

        return $status;
    }
}
