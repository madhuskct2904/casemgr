<?php


namespace App\Service\Participants;

use App\Entity\Accounts;
use App\Entity\ParticipantDirectoryColumns;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

/**
 * Class ParticipantDirectoryColumnsService
 * @package App\Service\Participants
 */
class ParticipantDirectoryColumnsService
{
    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * ParticipantDirectoryService constructor.
     *
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * @param Accounts $account
     * @param array $customColumns
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateColumns(Accounts $account, array $customColumns): void
    {

        /** @var ParticipantDirectoryColumns $pdc */
        $pdc = $this->em->getRepository('App:ParticipantDirectoryColumns')->findOneBy(['account' => $account]);

        if (!$pdc) {
            return;
        }

        $pdcColumns = json_decode($pdc->getColumns(), true);

        if (!is_array($pdcColumns)) {
            return;
        }

        $columnsKeys = array_map(function ($column) {
            return $column['key'];
        }, $customColumns);
        $positions = [8, 9];

        $keyableColumns = [];
        foreach ($customColumns as $customColumn) {
            $keyableColumns[$customColumn['key']] = $customColumn;
        }

        foreach ($positions as $position) {
            if (!isset($pdcColumns[$position]['key'])) {
                continue;
            }
            if (!in_array($pdcColumns[$position]['key'], $columnsKeys)) {
                // custom column deleted - delete participant directory column
                $pdcColumns[$position] = [
                    'label'    => '',
                    'field'    => '',
                    'sticky'   => false,
                    'custom'   => true,
                    'position' => $position,
                    'sortable' => false
                ];
            // custom column exists
            } else {
                $pdcColumns[$position]['label'] = $keyableColumns[$pdcColumns[$position]['key']]['label'];
                $pdcColumns[$position]['field'] = $keyableColumns[$pdcColumns[$position]['key']]['value'];
            }
        }

        $pdcColumnsStr = json_encode($pdcColumns);
        $pdc->setColumns($pdcColumnsStr);
        $this->em->persist($pdc);
        $this->em->flush();
    }
}
