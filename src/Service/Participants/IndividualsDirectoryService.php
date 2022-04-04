<?php

namespace App\Service\Participants;

use App\Entity\Accounts;
use App\Entity\Users;
use App\Enum\ParticipantDirectoryContext;
use App\Enum\ParticipantStatus;
use App\Utils\Helper;
use DateTime;
use Doctrine\ORM\NonUniqueResultException;

class IndividualsDirectoryService extends ParticipantDirectoryService
{
    protected $orderBy = 'last_name';
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
     * @param bool $get_count
     *
     * @return array
     */
    private function query(bool $get_count)
    {
        $query = $this->em->createQueryBuilder();

        if ($get_count === false) {
            $query->select('u')->from('App\Entity\Users', 'u');
        } else {
            $query->select('count(u)')->from('App\Entity\Users', 'u');
        }

        $query->innerJoin('App\Entity\UsersData', 'ud', 'WITH', 'u.id = ud.user');

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
                ->andWhere($query->expr()->andX(
                    $query->expr()->orX(
                        $query->expr()->like(
                            $query->expr()->concat('ud.first_name', $query->expr()->concat($query->expr()->literal(' '), 'ud.last_name')), $query->expr()->literal($this->filters['keyword'] . '%')
                        ),
                        $query->expr()->like(
                            $query->expr()->concat('ud.last_name', $query->expr()->concat($query->expr()->literal(' '), 'ud.first_name')), $query->expr()->literal($this->filters['keyword'] . '%')
                        )
                    )
                ))
                ->orWhere('ud.last_name LIKE :name')
                ->orWhere('ud.system_id LIKE :system_id')
                ->orWhere('ud.organizationId LIKE :organization_id')
                ->setParameter('organization_id', '%' . $this->filters['keyword'] . '%')
                ->setParameter('system_id', '%' . $this->filters['keyword'] . '%')
                ->setParameter('name', '%' . $this->filters['keyword'] . '%');
        }

        if ($this->has('gender')) {
            $query
                ->andWhere('ud.gender = :gender')
                ->setParameter('gender', $this->filters['gender']);
        }

        if ($this->has('date_birth')) {
            $query
                ->andWhere('ud.date_birth LIKE :date_birth')
                ->setParameter('date_birth', '%' . $this->filters['date_birth'] . '%');
        }

        if ($this->has('system_id')) {
            $query
                ->andWhere('ud.system_id LIKE :system_id')
                ->setParameter('system_id', '%' . $this->filters['system_id'] . '%');
        }

        if ($this->has('manager_id')) {
            $query->andWhere('ud.case_manager = :manager_id')
                ->orWhere('ud.case_manager_secondary = :manager_id')
                ->setParameter('manager_id', $this->filters['manager_id']);
        }

        if ($this->has('case')) {
            $query->innerJoin('App\Entity\UsersData', 'udm', 'WITH', 'udm.user = ud.case_manager OR udm.user = ud.case_manager_secondary');

            $query
                ->andWhere($query->expr()->andX(
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
                ->andWhere('ud.statusLabel = :status')
                ->setParameter('status', $this->filters['status']);
        }

        if ($this->has('organization_id')) {
            $query
                ->andWhere('ua.systemId LIKE :organization_id')
                ->setParameter('organization_id', '%' . $this->filters['organization_id'] . '%');
        }

        if (isset($this->filters['notdismissed'])) {
            $query
                ->andWhere('ud.status != :dismissed OR ud.status IS NULL')
                ->setParameter('dismissed', ParticipantStatus::DISMISSED);
        }


        if (isset($this->filters['ignorewithemptystatus'])) {
            $query->andWhere('ud.status IS NOT NULL');
        }

        if (isset($this->filters['for_messaging'])) {
            $query->andWhere('ud.phone_number IS NOT NULL');
            $query->andWhere("ud.phone_number != ''");
        }

        if (isset($this->columnFilter) && count($this->columnFilter)) {
            $this->filterByColumns($query);
        }

        if (isset($this->programStatusFilter) && count($this->programStatusFilter)) {
            $query->andWhere('ud.statusLabel IN(:statusLabels)')
                ->setParameter('statusLabels', $this->programStatusFilter);
        }

        /** Ordering */

        $this->resultsOrder($query);

        /** Get data */
        if ($get_count === false) {
            /** @var Users[] $results */
            $results = $this->paginate($query, $this->filters['current_page'], $this->filters['limit']);
            $data = [];

            foreach ($results as $result) {
                $resultData   = $result->getData();
                $managerData  = null;
                $managerData2 = null;

                if (null === $resultData) {
                    continue;
                }

                $managerData = $this->em->getRepository('App:UsersData')
                    ->findOneBy(
                        [
                            'user' => (int) $resultData->getCaseManager(),
                        ]
                    )
                ;

                $managerData2 = $this->em->getRepository('App:UsersData')
                    ->findOneBy(
                        [
                            'user' => (int) $resultData->getCaseManagerSecondary(),
                        ]
                    )
                ;

                $userAccounts  = $result->getAccounts();
                $organizations = $this->getUserOrganizations($userAccounts);
                $customData    = $this->getCustomData($userAccounts, $result);

                $item = [
                    'id'                     => $result->getId(),
                    'participant_name'       => $resultData->getFullName(),
                    'date_of_birth'          => $resultData->getDateBirth(),
                    'gender'                 => $resultData->getGender(),
                    'system_id'              => $resultData->getSystemId(),
                    'case_manager'           => null !== $managerData ? $managerData->getFullName() : '',
                    'case_manager_secondary' => null !== $managerData2 ? $managerData2->getFullName() : '',
                    'status'                 => $resultData->getStatusLabel(),
                    'organization_id'        => $resultData->getOrganizationId(),
                    'date_completed'         => $resultData->getDateCompleted(),
                    'organization'           => $organizations,
                ];

                $data[] = array_merge($item, $customData);
            }
        } else {
            $data = $query->getQuery()->getSingleScalarResult();
        }

        return $data;
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
                        ->andWhere('ud.date_completed LIKE :columnDateCompleted')
                        ->setParameter('columnDateCompleted', '%' . $dateStr . '%');
                    break;
                case 'participant_name':
                    $query
                        ->andWhere($query->expr()->andX(
                            $query->expr()->orX(
                                $query->expr()->like(
                                    $query->expr()->concat(
                                        'ud.first_name',
                                        $query->expr()->concat($query->expr()->literal(' '), 'ud.last_name')
                                    ),
                                    $query->expr()->literal('%' . $value . '%')
                                ),
                                $query->expr()->like(
                                    $query->expr()->concat(
                                        'ud.last_name',
                                        $query->expr()->concat($query->expr()->literal(' '), 'ud.first_name')
                                    ),
                                    $query->expr()->literal('%' . $value . '%')
                                )
                            )
                        ));
                    break;
                case 'date_of_birth.date':
                    $dateStr = $this->convertDateToDashesString($value);
                    $query
                        ->andWhere('ud.date_birth LIKE :columnBirthDate')
                        ->setParameter('columnBirthDate', '%' . $dateStr . '%');
                    break;
                case 'gender':
                    $query->andWhere('ud.gender LIKE :columnGender')->setParameter('columnGender', '%' . $value . '%');
                    break;
                case 'system_id':
                    $query->andWhere('ud.system_id LIKE :columnSystemId')->setParameter('columnSystemId', '%' . $value . '%');
                    break;
                case 'organization_id':
                    $query
                        ->andWhere('ud.organizationId LIKE :columnOrganizationId')
                        ->setParameter('columnOrganizationId', '%' . $value . '%');
                    break;
                case 'case_manager':
                    $query->innerJoin('App\Entity\UsersData', 'udm', 'WITH', 'udm.user = ud.case_manager');
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
                    $query->innerJoin('App\Entity\UsersData', 'udm', 'WITH', 'udm.user = ud.case_manager_secondary');
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
                    $query->andWhere('ud.statusLabel LIKE :columnStatus')->setParameter('columnStatus', '%' . $value . '%');
                    break;
                default: // custom fields
                    $this->filterCustomFieldsCount++;

                    $modules = $this->em->getRepository('App:Modules')->findBy(['key' => ['participants_profile', 'participants_assignment']]);

                    $query->leftJoin('App\Entity\FormsData', 'fd' . $this->filterCustomFieldsCount, 'WITH', 'fd' . $this->filterCustomFieldsCount . '.element_id = u.id')
                        ->andWhere('fd' . $this->filterCustomFieldsCount . '.module IN (:modules)')
                        ->andWhere('fd' . $this->filterCustomFieldsCount . '.account_id IN (:accounts)')
                        ->setParameter('modules', $modules)
                        ->setParameter('accounts', count($this->accounts) ? $this->accounts : $this->filters['account'])
                        ->leftJoin('App\Entity\FormsValues', 'fv' . $this->filterCustomFieldsCount, 'WITH', 'fd' . $this->filterCustomFieldsCount . '.id = fv' . $this->filterCustomFieldsCount . '.data')
                        ->andWhere($query->expr()->andX(
                            $query->expr()->like('fv' . $this->filterCustomFieldsCount . '.name', ':fvName' . $this->filterCustomFieldsCount),
                            $query->expr()->like('fv' . $this->filterCustomFieldsCount . '.value', ':fvValue' . $this->filterCustomFieldsCount)
                        ))->setParameter('fvName' . $this->filterCustomFieldsCount, $column . '%')
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
                $orderBy = 'ud.date_completed';
                break;
            case 'participant_name':
                $orderBy = 'ud.last_name';
                break;
            case 'date_of_birth.date':
                $orderBy = 'ud.date_birth';
                break;
            case 'organization_id':
                $orderBy = 'ud.organizationId';
                break;
            case 'status':
                $orderBy = 'ud.statusLabel';
                break;
            case 'last_name':
                $query->orderBy('ud.last_name', $this->orderDir)
                    ->addOrderBy('ud.first_name', $this->orderDir)
                    ->andWhere('u.type = :user_type')
                    ->setParameter('user_type', 'participant');
                return;
            case 'case_manager':
                $query->leftJoin('App\Entity\UsersData', 'udm', 'WITH', 'udm.user = ud.case_manager')
                    ->orderBy('udm.last_name', $this->orderDir)
                    ->addOrderBy('udm.first_name', $this->orderDir);
                return;
                break;
            case 'case_manager_secondary':
                $query->leftJoin('App\Entity\UsersData', 'udm', 'WITH', 'udm.user = ud.case_manager_secondary')
                    ->orderBy('udm.last_name', $this->orderDir)
                    ->addOrderBy('udm.first_name', $this->orderDir);
                return;
                break;
            case 'phone_number':
            case 'job_title':
                $orderBy = 'ud.' . $this->orderBy;
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
     * @param array $organizations
     * @return array
     */
    private function getUserOrganizations($userAccounts): array
    {
        $organizations = [];

        if ($userAccounts) {
            foreach ($userAccounts as $userAccount) {
                $organizations[] = [
                    'id' => $userAccount->getId(),
                    'name' => $userAccount->getOrganizationName()
                ];
            }
        }
        return $organizations;
    }

    /**
     * @return array
     */
    public function search(): array
    {
        return $this->query(false);
    }

    /**
     * @param string        $first_name
     * @param string        $last_name
     * @param string        $organization_id
     * @param Accounts      $account
     * @param DateTime|null $birthDate
     *
     * @return array
     * @throws NonUniqueResultException
     */
    public function findUniqueParticipants(
        string $first_name,
        string $last_name,
        string $organization_id,
        Accounts $account,
        ?DateTime $birthDate = null
    ): array {
        $first_name = substr($first_name, 0, 3);
        $last_name  = substr($last_name, 0, 3);

        $query = $this->em->createQueryBuilder();
        $query
            ->select('u')->from('App\Entity\Users', 'u')
            ->leftJoin('App\Entity\UsersData', 'ud', 'WITH', 'u.id = ud.user')
            ->setParameters(
                [
                    'first_name'      => $first_name,
                    'last_name'       => $last_name,
                    'organization_id' => $organization_id,
                ]
            )
            ->where(
                $query->expr()->eq(
                    $query->expr()->substring('ud.first_name', 1, 3),
                    ':first_name'
                )
            )
            ->andWhere(
                $query->expr()->eq(
                    $query->expr()->substring('ud.last_name', 1, 3),
                    ':last_name'
                )
            )
            ->andWhere('ud.organizationId = :organization_id')
            ->innerJoin('u.accounts', 'ua')
        ;

        // Birth date
        if (null !== $birthDate) {
            $query
                ->andWhere('ud.date_birth = :dateBirth')
                ->setParameter('dateBirth', $birthDate->format('Y-m-d'))
            ;
        }

        if (count($this->accounts)) {
            $query->andWhere('ua.id IN (:accountsIds)');
            $query->setParameter('accountsIds', $this->accounts);
        } else {
            $query->andWhere('ua.id = :account');
            $query->setParameter('account', $account->getId());
        }

        $users = $query->getQuery()->getResult();

        $data = [];

        foreach ($users as $result) {
            $managerData = null;
            $id = $result->getData()->getCaseManager();

            if ((int)$id) {
                $managerData = $this->em->getRepository('App:UsersData')->findOneBy(['user' => $id]);
            }

            $managerData2 = null;
            $id = $result->getData()->getCaseManagerSecondary();
            if ((int)$id) {
                $managerData2 = $this->em->getRepository('App:UsersData')->findOneBy(['user' => $id]);
            }

            $userAccounts = $result->getAccounts();

            $organizations = $this->getUserOrganizations($userAccounts);
            $customData = $this->getCustomData($userAccounts, $result);

            $data[] = array_merge([
                'id' => $result->getId(),
                'participant_name' => $result->getData()->getFullName(),
                'date_of_birth' => $result->getData()->getDateBirth(),
                'gender' => $result->getData()->getGender(),
                'system_id' => $result->getData()->getSystemId(),
                'case_manager' => $managerData ? $managerData->getFullName() : '',
                'case_manager_secondary' => $managerData2 ? $managerData2->getFullName() : '',
                'status' => $result->getData()->getStatusLabel(),
                'organization_id' => $result->getData()->getOrganizationId(),
                'date_completed' => $result->getData()->getDateCompleted(),
                'organization' => $organizations
            ], $customData);
        }

        if (!$this->getContext()) {
            $this->setContext(ParticipantDirectoryContext::PARTICIPANT_DIRECTORY);
        }

        return [
            'users' => $data,
            'columns' => array_values($this->getColumns($account, true))
        ];
    }

    /**
     * @param $phone
     * @param $user_id
     * @param $account
     *
     * @return bool
     */
    public function isUniqueOrNullParticipantPhone($phone, $user_id = null, Accounts $account = null)
    {
        if (!$number = Helper::convertPhone($phone)) {
            return true;
        }

        if ($account === null) {
            return true;
        }

        $qb = $this->em
            ->createQueryBuilder()
            ->select(['u.id', 'ud.phone_number'])
            ->from('App\Entity\UsersData', 'ud')
            ->andWhere('ud.phone_number = :number')
            ->setParameter('number', $number)
            ->leftJoin('ud.user', 'u')
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

    /**
     * @return array
     */
    public function getDefaultColumns(): array
    {
        $context = $this->getContext();

        if ($context === ParticipantDirectoryContext::GROUPS_FORMS || $context == ParticipantDirectoryContext::GROUPS_MESSAGING) {
            return [
                [
                    'label' => 'Participant Name',
                    'field' => 'participant_name',
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => false,
                    'position' => 1
                ],
                [
                    'label' => 'System ID',
                    'field' => 'system_id',
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => false,
                    'position' => 2
                ],
                [
                    'label' => 'Organization ID',
                    'field' => 'organization_id',
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => false,
                    'position' => 3
                ],
                [
                    'label' => 'Status',
                    'field' => 'status',
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => false,
                    'position' => 4
                ]
            ];
        }

        if ($context === ParticipantDirectoryContext::PARTICIPANT_DIRECTORY) {
            return [
                [
                    'label' => 'Date Completed',
                    'field' => "date_completed.date",
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => true,
                    'position' => 1
                ],
                [
                    'label' => 'Participant Name',
                    'field' => 'participant_name',
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => true,
                    'position' => 2
                ],
                [
                    'label' => 'Date of Birth',
                    'field' => 'date_of_birth.date',
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => false,
                    'position' => 3
                ],
                [
                    'label' => 'Gender',
                    'field' => 'gender',
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => false,
                    'position' => 4
                ],
                [
                    'label' => 'System ID',
                    'field' => 'system_id',
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => true,
                    'position' => 5
                ],
                [
                    'label' => 'Organization ID',
                    'field' => 'organization_id',
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => true,
                    'position' => 6
                ],
                [
                    'label' => 'Case Manager',
                    'field' => 'case_manager',
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => true,
                    'position' => 7
                ],
                [
                    'label' => 'Secondary Case Manager',
                    'field' => 'case_manager_secondary',
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => false,
                    'position' => 11
                ],
                [
                    'label' => 'Status',
                    'field' => 'status',
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => false,
                    'position' => 10
                ]
            ];
        }
    }

}
