<?php

namespace App\Command;

use App\Enum\ParticipantStatus;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixAssignmentsFixCaseManagerCommand extends Command
{
    protected ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:fix-assignments-fix-case-managers')
            ->setDescription('Remove duplicated forms data for assignments, fix case manager info.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $formData = $this->doctrine->getManager()->getRepository('App:FormsData')->findBy([
            'module' => [
                1,
                2,
                3
            ]
        ], ['id' => 'DESC']);

        $userForms = [];
        $duplicatedForms = [];

        $em = $this->doctrine->getManager();

        foreach ($formData as $item) {
            $assignmentId = $item->getAssignment() ? $item->getAssignment()->getId() : 'CURRENT';
            $moduleId = $item->getModule()->getId();

            if (isset($userForms[$item->getElementId()][$moduleId][$assignmentId])) {
                $duplicatedForms[] = $item->getId();
                $output->writeln('Removed form data ID: ' . $item->getId());
                $em->remove($item);
            }

            $userForms[$item->getElementId()][$moduleId][$assignmentId] = $item->getId();
        }

        $em->flush();

        $this->fixCaseManagers();
    }

    private function fixCaseManagers()
    {
        $formsData = $this->doctrine->getManager()->getRepository('App:FormsData')->findBy([
            'module' => [
                3
            ]
        ], ['id' => 'ASC']);

        $map = [];

        $em = $this->doctrine->getManager();

        // Get all additional user statuses which can be treated like Dismissed
        $usersData = $em->getRepository('App:UsersData');
        $dismissedStatuses = $usersData->createQueryBuilder('ud')
            ->select('ud.statusLabel')
            ->where('ud.status =:dismissedStatus')
            ->setParameter('dismissedStatus', ParticipantStatus::DISMISSED)
            ->distinct()
            ->getQuery()
            ->getResult();

        $statuses = ['Dismissed'];

        foreach ($dismissedStatuses as $dismissedStatus) {
            $statuses[] = $dismissedStatus['statusLabel'];
        }


        foreach ($formsData as $formData) {
            $form = $formData->getForm();
            $formMap = json_decode($form->getColumnsMap(), true);

            $userId = $formData->getElementId();
            $assignment = $formData->getAssignment();

            $managerId = null;

            if ($assignment) { // if this is assignment
                if (isset($map[$userId])) { // but user already has assignment
                    if ($map[$userId] > $assignment->getId()) { // but if this assignment has lower id (it's older)
                        continue; // do nothing
                    } // otherwise - good, proceed
                } else {
                    $map[$userId] = $assignment->getId(); // if user has no assigment - set it
                }

                if (in_array($assignment->getProgramStatus(), $statuses)) {
                    $managerId = '';
                }
            }

            foreach ($formMap as $mapItem) {
                if ($mapItem['name'] == 'primary_case_manager_id') {
                    $managerField = $mapItem['value'];

                    $values = $formData->getValues();

                    foreach ($values as $value) {
                        if ($value->getName() == $managerField) {
                            if ($managerId === null) {
                                $managerId = $value->getValue();
                            }

                            $user = $this->doctrine->getManager()->getRepository('App:Users')->find($formData->getElementId());
                            $userData = $user->getData();
                            $userData->setCaseManager($managerId ? $managerId : '');
                            $em->persist($userData);
                        }
                    }
                };
            }
        }

        $em->flush();
    }
}
