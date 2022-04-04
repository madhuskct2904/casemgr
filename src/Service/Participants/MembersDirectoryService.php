<?php

namespace App\Service\Participants;

use App\Entity\Accounts;
use App\Enum\AccountType;
use App\Enum\ParticipantDirectoryContext;
use App\Enum\ParticipantStatus;
use App\Utils\Helper;

class MembersDirectoryService extends ParticipantDirectoryService
{
    protected $orderBy = 'name';
    protected $orderDir = 'ASC';
    protected $filterCustomFieldsCount = 0;

    /**
     * @return int
     */
    public function resultsNum(): int
    {
        return $this->query(true);
    }

    /**
     * @return array
     */
    public function search(): array
    {
        return $this->query(false);
    }

    /**
     * @param bool $getCount
     *
     * @return array
     */
    private function query(bool $getCount)
    {
        $query = $this->em->createQueryBuilder();

        if ($getCount === false) {
            $query->select('u')->from('App\Entity\Users', 'u');
        } else {
            $query->select('count(u)')->from('App\Entity\Users', 'u');
        }

        $query->join('App\Entity\MemberData', 'md', 'WITH', 'u.id = md.user');

        if (count($this->accounts)) {
            $query->innerJoin('u.accounts', 'ua', 'WITH', 'ua.id IN(:accounts)')
                ->setParameter('accounts', $this->accounts);
        } elseif (isset($this->filters['account'])) {
            $query
                ->innerJoin('u.accounts', 'ua', 'WITH', 'ua.id = :account')
                ->setParameter('account', $this->filters['account']);
        } else {
            $query->innerJoin('u.accounts', 'ua');
        }

        if ($this->has('keyword')) {
            $query
                ->andWhere('md.name LIKE :name')
                ->orWhere('md.systemId LIKE :system_id')
                ->orWhere('md.organizationId LIKE :organization_id')
                ->setParameter('organization_id', '%' . $this->filters['keyword'] . '%')
                ->setParameter('system_id', '%' . $this->filters['keyword'] . '%')
                ->setParameter('name', '%' . $this->filters['keyword'] . '%');
        }

        if ($this->has('gender')) {
            $query
                ->andWhere('md.gender = :gender')
                ->setParameter('gender', $this->filters['gender']);
        }


        if ($this->has('system_id')) {
            $query
                ->andWhere('md.systemId LIKE :system_id')
                ->setParameter('system_id', '%' . $this->filters['system_id'] . '%');
        }

        if ($this->has('case')) {
            $query->innerJoin('App\Entity\UsersData', 'udm', 'WITH', 'udm.user = md.caseManager OR udm.user = md.case_manager_secondary');

            $query
                ->where($query->expr()->andX(
                    $query->expr()->orX(
                        $query->expr()->like(
                            $query->expr()->concat(
                                'udm.first_name',
                                $query->expr()->concat($query->expr()->literal(' '), 'udm.last_name')
                            ),
                            $query->expr()->literal($this->filters['case'] . '%')
                        ),
                        $query->expr()->like(
                            $query->expr()->concat(
                                'udm.last_name',
                                $query->expr()->concat($query->expr()->literal(' '), 'udm.first_name')
                            ),
                            $query->expr()->literal($this->filters['case'] . '%')
                        )
                    )
                ))
                ->orWhere('udm.last_name LIKE :case_manager')
                ->setParameter('case_manager', '%' . $this->filters['case'] . '%');
        }

        if ($this->has('status')) {
            $query
                ->andWhere('md.statusLabel = :status')
                ->setParameter('status', $this->filters['status']);
        }

        if ($this->has('organization_id')) {
            $query
                ->andWhere('ua.systemId LIKE :organization_id')
                ->setParameter('organization_id', '%' . $this->filters['organization_id'] . '%');
        }

        if (isset($this->filters['notdismissed'])) {
            $query
                ->andWhere('md.status != :dismissed OR md.status IS NULL')
                ->setParameter('dismissed', ParticipantStatus::DISMISSED);
        }


        if (isset($this->filters['ignorewithemptystatus'])) {
            $query->andWhere('md.status IS NOT NULL');
        }

        if (isset($this->filters['for_messaging'])) {
            $query->andWhere('ud.phoneNumber IS NOT NULL');
            $query->andWhere("ud.phoneNumber != ''");
        }

        if (isset($this->columnFilter) && count($this->columnFilter)) {
            $this->filterByColumns($query);
        }

        if (isset($this->programStatusFilter) && count($this->programStatusFilter)) {
            $query->andWhere('md.statusLabel IN(:statusLabels)')
                ->setParameter('statusLabels', $this->programStatusFilter);
        }

        /** Ordering */

        $this->resultsOrder($query);

        /** Get data */
        if ($getCount === false) {
            $results = $this->paginate($query, $this->filters['current_page'], $this->filters['limit']);
            $data = [];

            foreach ($results as $result) {
                $managerData = $this->em->getRepository('App:UsersData')->findOneBy([
                    'user' => (int)$result->getMemberData()->getCaseManager()
                ]);

                $userAccounts = $result->getAccounts();

                $organizations = $this->getUserOrganizations($userAccounts);
                $customData = $this->getCustomData($userAccounts, $result);

                $item = [
                    'id'               => $result->getId(),
                    'participant_name' => $result->getMemberData()->getName(),
                    'system_id'        => $result->getMemberData()->getSystemId(),
                    'case_manager'     => $managerData ? $managerData->getFullName() : '',
                    'status'           => $result->getMemberData()->getStatusLabel(),
                    'organization_id'  => $result->getMemberData()->getOrganizationId(),
                    'date_completed'   => $result->getMemberData()->getDateCompleted(),
                    'organization'     => $organizations

                ];

                $data[] = array_merge($item, $customData);
            }
        } else {
            $data = $query->getQuery()->getSingleScalarResult();
        }

        return $data;
    }

    public function findUniqueMembers(string $name, string $organization_id, Accounts $account): array
    {
        $name = substr($name, 0, 3);

        $query = $this->em->createQueryBuilder();

        $query->select('u')->from('App\Entity\Users', 'u');
        $query->leftJoin('App\Entity\MemberData', 'm', 'WITH', 'u.id = m.user');

        $query
            ->where(
                $query->expr()->eq(
                    $query->expr()->substring('m.name', 1, 3),
                    ':name'
                )
            );

        $query->andWhere('m.organizationId = :organization_id');

        $query->setParameter('name', $name);
        $query->setParameter('organization_id', $organization_id);

        $query->innerJoin('u.accounts', 'ua');

        if (count($this->accounts)) {
            $query->andWhere('ua.id IN (:accountsIds)');
            $query->setParameter('accountsIds', $this->accounts);
        } else {
            $query->andWhere('ua.id = :account');
            $query->setParameter('account', $account->getId());
        }

        $data = [];

        $users = $query->getQuery()->getResult();

        foreach ($users as $result) {
            if (!$result->getMemberData()) {
                continue;
            }

            $managerData = null;
            $id = $result->getMemberData()->getCaseManager();

            if ((int)$id) {
                $managerData = $this->em->getRepository('App:UsersData')->findOneBy(['user' => $id]);
            }

            $userAccounts = $result->getAccounts();

            $organizations = $this->getUserOrganizations($userAccounts);
            $customData = $this->getCustomData($userAccounts, $result);

            $data[] = array_merge([
                'id'               => $result->getId(),
                'participant_name' => $result->getMemberData()->getName(),
                'system_id'        => $result->getMemberData()->getSystemId(),
                'case_manager'     => $managerData ? $managerData->getFullName() : '',
                'status'           => $result->getMemberData()->getStatusLabel(),
                'organization_id'  => $result->getMemberData()->getOrganizationId(),
                'date_completed'   => $result->getMemberData()->getDateCompleted(),
                'organization'     => $organizations
            ], $customData);
        }

        if (!$this->getContext()) {
            $this->setContext(ParticipantDirectoryContext::PARTICIPANT_DIRECTORY);
        }

        return [
            'users'   => $data,
            'columns' => array_values($this->getColumns($account, true))
        ];
    }

    /**
     * @param $phone
     * @param $user_id
     * @param $account
     * @return bool
     */
    public function isUniqueOrNullMemberPhone($phone, $user_id = null, Accounts $account = null)
    {
        if (!$number = Helper::convertPhone($phone)) {
            return true;
        }

        if ($account === null) {
            return true;
        }

        $qb = $this->em
            ->createQueryBuilder()
            ->select(['u.id', 'md.phoneNumber'])
            ->from('App\Entity\MemberData', 'md')
            ->andWhere('md.phoneNumber = :number')
            ->setParameter('number', $number)
            ->leftJoin('md.user', 'u')
            ->andWhere('u.type = :participant')
            ->setParameter('participant', 'participant')
            ->innerJoin('u.accounts', 'ua')
            ->andWhere('ua.id = :account')
            ->setParameter('account', $account->getId());

        if ($user_id !== null) {
            $qb->andWhere('u.id != :user')
                ->setParameter('user', $user_id);
        }

        return count($qb->getQuery()->getArrayResult()) ? false : true;
    }

    public function getDefaultColumns(): array
    {
        $context = $this->getContext();

        if ($context === ParticipantDirectoryContext::GROUPS_FORMS || $context === ParticipantDirectoryContext::GROUPS_MESSAGING) {
            return [
                [
                    'label'         => 'Participant Name',
                    'field'         => 'participant_name',
                    'filterOptions' => ['enabled' => true],
                    'custom'        => false,
                    'sticky'        => false,
                    'position'      => 1
                ],
                [
                    'label'         => 'System ID',
                    'field'         => 'system_id',
                    'filterOptions' => ['enabled' => true],
                    'custom'        => false,
                    'sticky'        => false,
                    'position'      => 2
                ],
                [
                    'label'         => 'Organization ID',
                    'field'         => 'organization_id',
                    'filterOptions' => ['enabled' => true],
                    'custom'        => false,
                    'sticky'        => false,
                    'position'      => 3
                ],
                [
                    'label'         => 'Status',
                    'field'         => 'status',
                    'filterOptions' => ['enabled' => true],
                    'custom'        => false,
                    'sticky'        => false,
                    'position'      => 4
                ]
            ];
        }

        if ($context === ParticipantDirectoryContext::PARTICIPANT_DIRECTORY) {
            return [
                [
                    'label'         => 'Date Completed',
                    'field'         => "date_completed.date",
                    'filterOptions' => ['enabled' => true],
                    'custom'        => false,
                    'sticky'        => true,
                    'position'      => 1
                ],
                [
                    'label'         => 'Participant Name',
                    'field'         => 'participant_name',
                    'filterOptions' => ['enabled' => true],
                    'custom'        => false,
                    'sticky'        => true,
                    'position'      => 2
                ],
                [
                    'label'         => 'System ID',
                    'field'         => 'system_id',
                    'filterOptions' => ['enabled' => true],
                    'custom'        => false,
                    'sticky'        => true,
                    'position'      => 3
                ],
                [
                    'label'         => 'Organization ID',
                    'field'         => 'organization_id',
                    'filterOptions' => ['enabled' => true],
                    'custom'        => false,
                    'sticky'        => true,
                    'position'      => 4
                ],
                [
                    'label'         => 'Case Manager',
                    'field'         => 'case_manager',
                    'filterOptions' => ['enabled' => true],
                    'custom'        => false,
                    'sticky'        => true,
                    'position'      => 5

                ],
                [
                    'label'         => 'Status',
                    'field'         => 'status',
                    'filterOptions' => ['enabled' => true],
                    'custom'        => false,
                    'sticky'        => true,
                    'position'      => 8
                ]
            ];
        }
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     */
    private function filterByColumns(\Doctrine\ORM\QueryBuilder $query): void
    {
        foreach ($this->columnFilter as $column => $value) {
            if (empty($value)) {
                continue;
            }

            if (strstr($column, 'programs-checkbox-group') !== false) {
                $fvName = $column;
                $column = '_programs';
            }

            switch ($column) {
                case '_programs':
                    $query->innerJoin('App\Entity\FormsData', 'fd', 'WITH', 'fd.element_id = u.id')
                        ->innerJoin('App\Entity\FormsValues', 'fv', 'WITH', 'fd.id = fv.data')
                        ->innerJoin('App\Entity\Programs', 'p', 'WITH', 'p.id = fv.value')
                        ->andWhere('fv.name LIKE :fvName')
                        ->andWhere('p.name LIKE :programName')
                        ->setParameter('fvName', $fvName . '%')
                        ->setParameter('programName', '%' . $value . '%');
                    break;
                case 'date_completed.date':
                    $dateStr = $this->convertDateToDashesString($value);
                    $query
                        ->andWhere('md.dateCompleted LIKE :columnDateCompleted')
                        ->setParameter('columnDateCompleted', '%' . $dateStr . '%');
                    break;
                case 'participant_name':
                    $query
                        ->andWhere('md.name LIKE :columnName')
                        ->setParameter('columnName', '%' . $value . '%');
                    break;
                case 'system_id':
                    $query->andWhere('md.systemId LIKE :columnSystemId')->setParameter('columnSystemId', '%' . $value . '%');
                    break;
                case 'organization_id':
                    $query
                        ->andWhere('md.organizationId LIKE :columnOrganizationId')
                        ->setParameter('columnOrganizationId', '%' . $value . '%');
                    break;
                case 'case_manager':
                    $query->innerJoin('App\Entity\UsersData', 'udm', 'WITH', 'udm.user = md.case_manager');
                    $query
                        ->andWhere($query->expr()->andX(
                            $query->expr()->orX(
                                $query->expr()->like(
                                    $query->expr()->concat(
                                        'udm.first_name',
                                        $query->expr()->concat($query->expr()->literal(' '), 'udm.last_name')
                                    ),
                                    $query->expr()->literal('%' . $value . '%')
                                ),
                                $query->expr()->like(
                                    $query->expr()->concat(
                                        'udm.last_name',
                                        $query->expr()->concat($query->expr()->literal(' '), 'udm.first_name')
                                    ),
                                    $query->expr()->literal('%' . $value . '%')
                                )
                            )
                        ));
                    break;
                case 'case_manager_secondary':
                    $query->innerJoin('App\Entity\UsersData', 'udm', 'WITH', 'udm.user = md.case_manager_secondary');
                    $query
                        ->andWhere($query->expr()->andX(
                            $query->expr()->orX(
                                $query->expr()->like(
                                    $query->expr()->concat(
                                        'udm.first_name',
                                        $query->expr()->concat($query->expr()->literal(' '), 'udm.last_name')
                                    ),
                                    $query->expr()->literal('%' . $value . '%')
                                ),
                                $query->expr()->like(
                                    $query->expr()->concat(
                                        'udm.last_name',
                                        $query->expr()->concat($query->expr()->literal(' '), 'udm.first_name')
                                    ),
                                    $query->expr()->literal('%' . $value . '%')
                                )
                            )
                        ));
                    break;
                case 'status':
                    $query->andWhere('md.statusLabel LIKE :columnStatus')->setParameter('columnStatus', '%' . $value . '%');
                    break;
                default: // custom fields
                    $this->filterCustomFieldsCount++;

                    $modules = $this->em->getRepository('App:Modules')->findBy(['key' => ['members_profile','participants_assignment']]);

                    $query->leftJoin('App\Entity\FormsData', 'fd' . $this->filterCustomFieldsCount, 'WITH', 'fd' . $this->filterCustomFieldsCount . '.element_id = u.id')
                        ->andWhere('fd' . $this->filterCustomFieldsCount . '.module IN (:modules)')
                        ->andWhere('fd' . $this->filterCustomFieldsCount . '.account_id IN (:accounts)')
                        ->setParameter('modules', $modules)
                        ->setParameter('accounts', count($this->accounts) ? $this->accounts : $this->filters['account'])
                        ->leftJoin('App\Entity\FormsValues', 'fv' . $this->filterCustomFieldsCount, 'WITH', 'fd' . $this->filterCustomFieldsCount . '.id = fv' . $this->filterCustomFieldsCount . '.data')
                        ->andWhere($query->expr()->andX(
                            $query->expr()->like('fv' . $this->filterCustomFieldsCount . '.name', ':fvName' . $this->filterCustomFieldsCount),
                            $query->expr()->like('fv' . $this->filterCustomFieldsCount . '.value', ':fvValue' . $this->filterCustomFieldsCount)
                        ))
                        ->setParameter('fvName' . $this->filterCustomFieldsCount, $column . '%')
                        ->setParameter('fvValue' . $this->filterCustomFieldsCount, '%' . $value . '%');

                    break;
            }
        }
    }

    /**
     * @param \Doctrine\ORM\QueryBuilder $query
     */
    private function resultsOrder(\Doctrine\ORM\QueryBuilder $query): void
    {
        $query
            ->andWhere('u.type = :user_type')
            ->setParameter('user_type', 'participant');

        switch ($this->orderBy) {
            case 'date_completed.date':
                $orderBy = 'md.dateCompleted';
                break;
            case 'participant_name':
            case 'name':
                $orderBy = 'md.name';
                break;
            case 'organization_id':
                $orderBy = 'md.organizationId';
                break;
            case 'status':
                $orderBy = 'md.statusLabel';
                break;
            case 'case_manager':
                $query->leftJoin('App\Entity\UsersData', 'udm', 'WITH', 'udm.user = md.case_manager')
                    ->orderBy('udm.last_name', $this->orderDir)
                    ->addOrderBy('udm.first_name', $this->orderDir);
                return;
                break;
            case 'case_manager_secondary':
                $query->leftJoin('App\Entity\UsersData', 'udm', 'WITH', 'udm.user = md.case_manager_secondary')
                    ->orderBy('udm.last_name', $this->orderDir)
                    ->addOrderBy('udm.first_name', $this->orderDir);
                return;
                break;
            case 'phone_number':
            case 'job_title':
                $orderBy = 'md.' . $this->orderBy;
                break;
            default:
                $query->leftJoin('App\Entity\FormsData', 'ofd', 'WITH', 'ofd.element_id = u.id')
                    ->leftJoin('App\Entity\FormsValues', 'ofv', 'WITH', 'ofd.id = ofv.data')
                    ->andWhere('ofv.name LIKE :ofvName')->setParameter('ofvName', $this->orderBy . '%');

                if (strpos($this->orderBy, 'date-') === 0) {
                    $query->orderBy("STR_TO_DATE(ofv.value, '%m/%d/%Y')", $this->orderDir);
                } else {
                    $query->orderBy('ofv.value', $this->orderDir);
                }
                return;
        }

        $query->orderBy($orderBy, $this->orderDir);
    }

    /**
     * @param $userAccounts
     * @return array
     */
    private function getUserOrganizations($userAccounts): array
    {
        $organizations = [];

        if ($userAccounts) {
            foreach ($userAccounts as $userAccount) {
                $organizations[] = [
                    'id'   => $userAccount->getId(),
                    'name' => $userAccount->getOrganizationName()
                ];
            }
        }
        return $organizations;
    }
}
