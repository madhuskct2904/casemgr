<?php

namespace App\Service;

use App\Entity\Accounts;
use App\Entity\Users;
use App\Enum\ParticipantStatus;
use Doctrine\ORM\EntityManagerInterface;

class CaseloadWidgetService
{
    protected $em;
    protected $assignmentFormsService;

    public function __construct(EntityManagerInterface $entityManager, AssignmentFormsService $assignmentFormsService)
    {
        $this->em                     = $entityManager;
        $this->assignmentFormsService = $assignmentFormsService;
    }

    public function getCaseloadWidgetDataForManager(Users $manager, Accounts $account)
    {
        $data = $this->getParticipantsProgramStatuses($account);

        $summary = $this->getSummaryStatusesMap($account);

        foreach ($data as $v) {
            if (isset($v['program_status'])) {
                if (isset($v['primary_case_manager_id']) && (int)$v['primary_case_manager_id'] === $manager->getId()) {
                    if (isset($summary[$v['program_status']])) {
                        $summary[$v['program_status']]++;
                    }
                } elseif (isset($v['secondary_case_manager_id']) && (int)$v['secondary_case_manager_id'] === $manager->getId()) {
                    if (isset($summary[$v['program_status']])) {
                        $summary[$v['program_status']]++;
                    }
                }
            }
        }

        return $summary;
    }

    public function getCaseloadWidgetTotals(Accounts $account)
    {
        $data = $this->getParticipantsProgramStatuses($account);

        $caseManagers = $this->em
            ->createQueryBuilder()
            ->select('ud.first_name', 'ud.last_name', 'u.id')
            ->from('App\Entity\Users', 'u')
            ->leftJoin('u.individualData', 'ud')
            ->innerJoin('u.credentials', 'uc')
            ->andWhere('uc.enabled = :enabled')
            ->setParameter('enabled', 1)
            ->andWhere('uc.account = :account')
            ->setParameter('account', $account->getId())
            ->andWhere('uc.access IN(:roles)')
            ->setParameter('roles', [Users::ACCESS_LEVELS['CASE_MANAGER'], Users::ACCESS_LEVELS['SUPERVISOR']])
            ->orderBy('ud.first_name', 'ASC')
            ->getQuery()
            ->getResult();

        $keys    = [];
        $summary = [];

        foreach ($caseManagers as $k => $caseManager) {
            $keys[$caseManager['id']] = $k;
            $summary[$k]              = [
                'fullName' => sprintf('%s %s', $caseManager['first_name'], $caseManager['last_name']),
                'total'    => 0,
                'id'       => $caseManager['id']
            ];
        }

        foreach ($data as $v) {
            if (isset($v['primary_case_manager_id']) && isset($keys[$v['primary_case_manager_id']]) && isset($summary[$keys[$v['primary_case_manager_id']]])) {
                $summary[$keys[$v['primary_case_manager_id']]]['total']++;
            }
            if (isset($v['secondary_case_manager_id']) && isset($keys[$v['secondary_case_manager_id']]) && isset($summary[$keys[$v['secondary_case_manager_id']]])) {
                $summary[$keys[$v['secondary_case_manager_id']]]['total']++;
            }
        }

        return $summary;
    }

    /**
     * @param Accounts $account
     *
     * @return array
     */
    protected function getSummaryStatusesMap(Accounts $account): array
    {
        $organizationProgramStatuses = $this->assignmentFormsService->getProgramStatusesForOrganization($account);
        $statusesWidget              = [];

        foreach ($organizationProgramStatuses as $formId => $formStatuses) {
            $form = $this->em->getRepository('App\Entity\Forms')->find($formId);

            if (! $form) {
                continue;
            }

            $this->assignmentFormsService->setForm($form);

            foreach ($formStatuses as $statusIdx => $status) {
                if ($this->assignmentFormsService->getParticipantStatusByProgramStatus($status) !== ParticipantStatus::DISMISSED) {
                    $statusesWidget[$status] = 0;
                }
            }
        }

        ksort($statusesWidget);

        return $statusesWidget;
    }

    /**
     * @param Accounts $account
     *
     * @return array
     */
    protected function getParticipantsProgramStatuses(Accounts $account): array
    {
        $data = [];

        $qb = $this->em->createQueryBuilder()
                       ->select('fd')
                       ->from('App\Entity\FormsData', 'fd');

        $qb->leftJoin('App\Entity\Forms', 'f', 'WITH', 'f.id = fd.form');
        $qb->leftJoin('App\Entity\Modules', 'm', 'WITH', 'm.id = fd.module');
        $qb->leftJoin('App\Entity\FormsValues', 'fv', 'WITH', 'fv.data = fd.id');

        // exclude removed users - for dev db bugs
        $qb->innerJoin('App\Entity\Users', 'u', 'WITH', 'u.id = fd.element_id');
        // include only if participant has this account
        $qb
            ->innerJoin('u.accounts', 'ua')
            ->andWhere('ua.id = :account')
            ->setParameter('account', $account->getId());

        $qb->andWhere('m.key = :module');
        $qb->andWhere('fv.value != :empty');
        $qb->andWhere('fd.assignment IS NULL');

        $qb->setParameter('module', 'participants_assignment');
        $qb->setParameter('empty', '');


        foreach ($qb->getQuery()->getResult() as $formData) {
            $columnsMap         = json_decode($formData->getForm()->getColumnsMap(), true);
            $systemConditionals = $formData->getForm()->getSystemConditionals() ? json_decode(
                $formData->getForm()->getSystemConditionals(),
                true
            ) : null;

            $flatMap = [];

            if (is_array($columnsMap)) {
                foreach ($columnsMap as $map) {
                    $flatMap[$map['name']] = $map['value'];
                }

                if (isset($flatMap['primary_case_manager_id']) && $systemConditionals) {
                    isset($systemConditionals['programStatus']) ? $programStatusField = $systemConditionals['programStatus']['field'] : null;

                    $programStatusLabel = null;
                    $manager            = null;
                    $manager2           = null;

                    foreach ($formData->getValues() as $value) {
                        if ($value->getName() == $programStatusField) {
                            $programStatusLabel = $value->getValue();
                        }

                        if ($value->getName() == $flatMap['primary_case_manager_id']) {
                            $manager = $value->getValue();
                        }

                        if ($value->getName() == $flatMap['secondary_case_manager_id']) {
                            $manager2 = $value->getValue();
                        }
                    }

                    if ($programStatusLabel && $manager) {
                        $data[$formData->getId()] = [
                            'program_status'            => $programStatusLabel,
                            'primary_case_manager_id'   => $manager,
                            'secondary_case_manager_id' => $manager2
                        ];
                    }
                }
            }
        }

        return $data;
    }
}
