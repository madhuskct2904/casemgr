<?php

namespace App\Domain\Participant;

use App\Entity\Assignments;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;

class ParticipantManager
{
    protected $em;
    protected $participantUser;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function setParticipantUser(Users $participantUser): void
    {
        $this->participantUser = $participantUser;
    }

    public function getCurrentAssignment(): ?Assignments
    {
        $module = $this->em->getRepository('App:Modules')->findOneBy(['key' => 'participants_assignment']);

        $participantId = $this->participantUser->getId();
        $assignmentFormsData = $this->em->getRepository('App:FormsData')->findOneBy([
            'element_id' => $participantId,
            'module'     => $module
        ], ['created_date' => 'DESC']);

        return $assignmentFormsData ? $assignmentFormsData->getAssignment() : null;
    }
}
