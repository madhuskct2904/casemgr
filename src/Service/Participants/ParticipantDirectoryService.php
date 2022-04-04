<?php

namespace App\Service\Participants;

use App\Domain\FormValues\ParticipantServiceException;
use App\Entity\Accounts;
use App\Entity\ParticipantDirectoryColumns;
use App\Enum\ParticipantDirectoryContext;
use App\Enum\ParticipantType;
use Aws\DirectConnect\Exception\DirectConnectException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Tools\Pagination\Paginator;

abstract class ParticipantDirectoryService implements ParticipantDirectoryServiceInterface
{
    protected $columnFilter = [];
    protected $filters = [];
    protected $programStatusFilter = [];
    protected $em;
    protected $accounts = [];
    protected $context;
    protected $dateFormat = 'MM/DD/YYYY';

    /**
     * ParticipantDirectoryService constructor.
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    /**
     * @param $dql
     * @param int $page
     * @param int $limit
     *
     * @return Paginator
     */
    public function paginate($dql, int $page = 1, int $limit = 20)
    {
        $paginator = new Paginator($dql);

        $paginator->getQuery()
            ->setFirstResult($limit * ($page - 1))// Offset
            ->setMaxResults($limit);// Limit

        return $paginator;
    }


    /**
     * @param string $name
     * @param mixed $value
     */
    public function set(string $name, $value)
    {
        $this->filters[$name] = $value;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function has(string $name): bool
    {
        if (isset($this->filters[$name]) === false) {
            return false;
        }

        if ($this->filters[$name] === null) {
            return false;
        }

        return true;
    }

    /**
     * @param string $orderBy
     */
    public function setOrderBy(string $orderBy): void
    {
        if (!strlen($orderBy)) {
            $orderBy = 'last_name';
        };

        $this->orderBy = $orderBy;
    }

    /**
     * @param string $orderDir
     */
    public function setOrderDir(string $orderDir): void
    {
        $orderDir = strtoupper($orderDir);

        if (!in_array($orderDir, ['ASC', 'DESC'])) {
            $this->orderDir = 'ASC';
            return;
        }

        $this->orderDir = $orderDir;
    }

    /**
     * @return array
     */
    public function getColumnFilter(): array
    {
        return $this->columnFilter;
    }

    /**
     * @param array $columnFilter
     */
    public function setColumnFilter(array $columnFilter): void
    {
        $this->columnFilter = array_filter($columnFilter);
    }

    public function setFilterProgramStatus(array $programStatuses): void
    {
        $this->programStatusFilter = $programStatuses;
    }

    /**
     * @param Accounts|null $account
     *
     * @param bool $withoutEmpty
     *
     * @return array
     */
    public function getColumns(?Accounts $account = null, bool $withoutEmpty = false): array
    {
        $data = [];
        $context = $this->getContext();

        for ($i = 0; $i < 10; $i++) {
            $data[$i] =
                [
                    'label' => '',
                    'field' => '',
                    'filterOptions' => ['enabled' => true],
                    'custom' => false,
                    'sticky' => false,
                    'position' => $i + 1
                ];
        }

        if ($account === null) {
            return array_replace($data, $this->getDefaultColumns());
        }

        $columns = $this->em->getRepository(ParticipantDirectoryColumns::class)->findOneBy([
            'account' => $account,
            'context' => $context
        ]);

        if (!$columns) {
            $data = array_replace($data, $this->getDefaultColumns());
        } else {
            $data = array_replace($data, json_decode($columns->getColumns(), true));
        }

        if (!$this->accountHasMappedSecondaryCaseManager($account)) {
            foreach ($data as $key => $column) {
                if ($column['field'] == 'case_manager_secondary') {
                    unset($data[$key]);
                    continue;
                }
            }
        }

        if (count($this->accounts)) {
            $data = array_merge($data, $this->getColumnsIfSearchInMultipleAccounts(count($data)));
        }

        foreach ($data as $key => $column) {
            if (isset($column['custom']) && $column['custom'] === true) {
                $data[$key]['sortable'] = true;
                $data[$key]['filterOptions'] = ['enabled' => true];
            }
        }

        if ($withoutEmpty) {
            foreach ($data as $key => $item) {
                if (!isset($item['label']) || empty($item['label'])) {
                    unset($data[$key]);
                }
            }
            $data = array_values($data);
        }

        usort($data, function ($col1, $col2) {
            return $col1['position'] <=> $col2['position'];
        });

        return $data;
    }

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(string $context): void
    {
        if (!ParticipantDirectoryContext::isValidValue($context)) {
            throw new DirectConnectException('Invalid context');
        }

        $this->context = $context;
    }

    public function getDefaultColumns(): array
    {
        return [];
    }

    /**
     * @param Accounts|null $account
     * @return bool
     */
    protected function accountHasMappedSecondaryCaseManager(?Accounts $account): bool
    {
        if (!$account) {
            return false;
        }

        $module = $this->em->getRepository('App:Modules')->findOneBy(['role' => 'assignment']);
        $assigmentForm = $this->em->getRepository('App:Forms')->findByModuleAndAccount($module, $account, false, true);

        if ($assigmentForm) {
            $assigmentForm = end($assigmentForm);
            $columnsMap = json_decode($assigmentForm->getColumnsMap(), true);

            if (is_array($columnsMap)) {
                foreach ($columnsMap as $item) {
                    if (isset($item['name']) && $item['name'] == 'secondary_case_manager_id' && isset($item['value']) && !empty($item['value'])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function getColumnsIfSearchInMultipleAccounts(int $startingPosition): array
    {
        return [
            [
                'label' => 'Organization',
                'field' => 'organization',
                'filterOptions' => ['enabled' => true],
                'custom' => false,
                'sticky' => true,
                'position' => $startingPosition + 1
            ],
            [
                'label' => 'Action',
                'field' => 'action',
                'filterOptions' => ['enabled' => true],
                'custom' => false,
                'sticky' => true,
                'position' => $startingPosition + 2
            ]
        ];
    }

    public function setAccounts($accounts)
    {
        $this->accounts = $accounts;
    }

    /**
     * @param Accounts $account
     * @param string $columns
     * @param string $context
     *
     * @throws ORMException
     * @throws ParticipantServiceException
     * @throws OptimisticLockException
     */
    public function saveParticipantDirectoryColumns(Accounts $account, string $columns): void
    {
        $context = $this->getContext();
        $item = $this->em->getRepository(ParticipantDirectoryColumns::class)->findOneBy([
            'account' => $account,
            'context' => $context
        ]);

        if (!$item) {
            $item = new ParticipantDirectoryColumns();
            $item->setAccount($account);
            $item->setContext($context);
        }

        $columns = \json_decode($columns, true);

        foreach ($columns as $position => $column) {
            $columns[$position]['position'] = $position;
        }

        $item->setColumns(\json_encode($columns));

        $this->em->persist($item);
        $this->em->flush();
    }

    /**
     * @param $userAccounts
     * @param $result
     * @return array
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    protected function getCustomData($userAccounts, $result): array
    {
        $customData = [];

        if (count($userAccounts) > 0) {
            $customColumns = $this->getCustomFormColumns($userAccounts[0], $this->getContext());

            foreach ($customColumns as $customColumn) {
                $fieldName = $customColumn['value'];
                $customData[$fieldName] = $this->em->getRepository('App:Users')->getCustomFieldData($result, $fieldName);
            }
        }
        return $customData;
    }

    /**
     * @param Accounts $account
     *
     * @return array
     */
    public function getCustomFormColumns(Accounts $account): array
    {
        $modules = [];

        if ($account->getParticipantType() == ParticipantType::MEMBER) {
            $modules[] = $this->em->getRepository('App:Modules')->findOneBy(['key' => 'members_profile']);
        }

        if ($account->getParticipantType() == ParticipantType::INDIVIDUAL) {
            $modules[] = $this->em->getRepository('App:Modules')->findOneBy(['key' => 'participants_profile']);
        }

        $modules[] = $this->em->getRepository('App:Modules')->findOneBy(['key' => 'participants_assignment']);

        if (!count($modules)) {
            return [];
        }

        $qb = $this->em->createQueryBuilder()
            ->select('f.custom_columns')
            ->from('App\Entity\Forms', 'f')
            ->where('f.module IN (:modules)');

        $qb->innerJoin('f.accounts', 'a', 'WITH', 'a.id = :account');
        $qb->setParameter('account', $account->getId());
        $qb->setParameter('modules', $modules);

        $columns = [];

        foreach ($qb->getQuery()->getResult() as $form) {
            $formColumns = json_decode($form['custom_columns'], true);
            if ($formColumns) {
                $columns = array_merge($columns, $formColumns);
            }
        }

        return $columns;
    }

    /**
     * @param $value
     * @return string
     */
    protected function convertDateToDashesString($value): string
    {
        $dateStr = $value;
        $dateStrSplited = explode('/', $value);
        $formatSplited = explode('/', $this->getDateFormat());
        $formatInverse = array_flip($formatSplited);

        if (count($dateStrSplited) == 3) {
            return $dateStrSplited[$formatInverse['Y']] . '-' . sprintf('%02d', $dateStrSplited[$formatInverse['m']]) . '-' . sprintf('%02d', $dateStrSplited[$formatInverse['d']]);
        }

        if (count($dateStrSplited) == 2) {
            if (strlen($dateStrSplited[1]) > 2) {
                if ($dateStrSplited[0] > 12) {
                    return sprintf('%04d', $dateStrSplited[1]) . '-__-' . sprintf('%02d', $dateStrSplited[0]);
                }

                return sprintf('%04d', $dateStrSplited[1]) . '-' . sprintf('%02d', $dateStrSplited[0]);
            }

            if ($formatInverse['m'] == 0 && $formatInverse['d'] == 1) {
                return sprintf('%02d', $dateStrSplited[0]) . '-' . sprintf('%02d', $dateStrSplited[1]);
            }

            if ($formatInverse['m'] == 1 && $formatInverse['d'] == 0) {
                return sprintf('%02d', $dateStrSplited[0]) . '-' . sprintf('%02d', $dateStrSplited[1]);
            }
        }

        return $dateStr;
    }

    public function getDateFormat(): string
    {
        return $this->dateFormat;
    }

    public function setDateFormat(string $dateFormat): void
    {
        $this->dateFormat = $dateFormat;
    }
}
