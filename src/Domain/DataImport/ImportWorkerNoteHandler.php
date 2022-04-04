<?php

namespace App\Domain\DataImport;

use App\Domain\DataImport\ImportNoteHandler;
use App\Entity\Assignments;
use App\Entity\CaseNotes;
use App\Enum\ParticipantStatus;
use Doctrine\ORM\EntityManagerInterface;

final class ImportWorkerNoteHandler extends BaseImportWorkerStrategy
{
    private $participantUserId = null;
    private $participantUser = null;
    protected $importHandler;

    public function __construct(EntityManagerInterface $em, ImportNoteHandler $importHandler)
    {
        $this->em = $em;
        $this->importHandler = $importHandler;
    }

    public function importCsvRow(array $csvRow): array
    {
        $row = $this->parseCsvRow($csvRow);
        $keyFieldValue = $this->getKeyFieldValue($csvRow);

        if (!$keyFieldValue) {
            throw new ImportWorkerException('Key field is undefined! Can\'t import.');
        }

        $keyField = $this->getImportHandler()->getImportKeyField();
        $this->participantUserId = $this->findParticipantIdByKeyField($keyField['formId'], $keyField['fieldInForm'], $keyFieldValue);
        $this->participantUser = $this->em->getRepository('App:Users')->find($this->participantUserId);

        if (!$this->participantUser) {
            throw new ImportWorkerException('Wrong participant!');
        }

        $manager = $this->em->getRepository('App:Users')->find($this->participantUser->getData()->getCaseManager());

        $communicationNotes = new CaseNotes();

        $type = '';

        if (isset($row['type'])) {
            switch ($row['type']) {
                case 'Collateral Contact':
                    $type = 'collateral';
                    break;
                case 'Email':
                    $type = 'email';
                    break;
                case 'In-Person':
                    $type = 'person';
                    break;
                case 'Phone':
                    $type = 'phone';
                    break;
                case 'Social/Messenger':
                    $type = 'social';
                    break;
                case 'Text':
                    $type = 'text';
                    break;
            }
        }

        $communicationNotes
            ->setParticipant($this->participantUser)
            ->setAssignment($this->getAssignment())
            ->setCreatedBy(null)
            ->setManager($manager)
            ->setNote($row['note'] ?? '')
            ->setType($type);

        $importCompletedDate = $this->getCompletedAt($row);

        if ($importCompletedDate) {
            $communicationNotes->setCreatedAt($importCompletedDate);
        }

        $this->em->persist($communicationNotes);
        $this->em->flush();

        return ['id' => $communicationNotes->getId()];
    }

    private function getAssignment(): ?Assignments
    {
        if ($this->participantUser->getData()->getStatus() === ParticipantStatus::ACTIVE) {
            return null;
        }

        return $this->em->getRepository('App:Assignments')->findLatestAssignmentForParticipant($this->participantUser);
    }

}
