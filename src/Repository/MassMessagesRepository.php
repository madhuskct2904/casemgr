<?php

namespace App\Repository;

use App\Entity\MassMessages;
use App\Entity\Messages;
use App\EntityRepository\EntityRepository;
use DateTimeZone;
use Doctrine\ORM\QueryBuilder;

/**
 * Class MassMessagesRepository
 * @package App\Repository
 */
class MassMessagesRepository extends EntityRepository
{
    private $orderBy = 'createdAt';
    private $orderDir = 'DESC';
    private $columnFilter = [];
    private $filters = [];
    private DateTimeZone $timezone;

    /**
     * @param string $name
     * @param mixed $value
     */
    public function set(string $name, $value)
    {
        $this->filters[$name] = $value;
    }

    public function setTimezone(DateTimeZone $timeZone): void
    {
        $this->timezone = $timeZone;
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

    private function query(bool $get_count)
    {
        $query = $this->createQueryBuilder('mm');

        if ($get_count === false) {
            $query->select(
                'mm'
            );
        } else {
            $query->select('count(mm)');
        }

        $query->leftJoin('App\Entity\Users', 'u', 'WITH', 'mm.user = u.id');
        $query->leftJoin('App\Entity\UsersData', 'ud', 'WITH', 'u.id = ud.user');

        if ($this->has('send_by')) {
            $query
                ->andWhere($query->expr()->andX(
                    $query->expr()->orX(
                        $query->expr()->like(
                            $query->expr()->concat(
                                'ud.first_name',
                                $query->expr()->concat($query->expr()->literal(' '), 'ud.last_name')
                            ),
                            $query->expr()->literal($this->filters['keyword'] . '%')
                        ),
                        $query->expr()->like(
                            $query->expr()->concat(
                                'ud.last_name',
                                $query->expr()->concat($query->expr()->literal(' '), 'ud.first_name')
                            ),
                            $query->expr()->literal($this->filters['keyword'] . '%')
                        )
                    )
                ))
                ->orWhere('ud.last_name LIKE :name')
                ->setParameter('name', '%' . $this->filters['send_by'] . '%');
        }

        if (isset($this->columnFilter) && count($this->columnFilter)) {
            $this->filterByColumns($query);
        }

        /** Ordering */

        $this->resultsOrder($query);

        /** Account filtering */
        $this->filterByAccount($query);

        /** Get data */
        if ($get_count === false) {
            $results = $this->paginate($query, $this->filters['current_page'], $this->filters['limit']);
            $data    = [];


            /** @var MassMessages $result */
            foreach ($results as $result) {
                $all  = $result->getMessages()->filter(function (Messages $message) {
                    return ($message->getStatus() != 'system_administrator');
                })->count();
                $sent = $result->getMessages()->filter(function (Messages $message) {
                    return ($message->getStatus() != 'error' && $message->getStatus() != 'system_administrator' && $message->getStatus() != null);
                })->count();

                $data[] = [
                    'id'         => $result->getId(),
                    'created_at' => $result->getCreatedAt($this->timezone)->format('m/d/Y h:i A'),
                    'send_by'    => $result->getUser()->getData()->getFullName(),
                    'message'    => $result->getBody(),
                    'status'     => sprintf("%s of %s Participants", $sent, $all)
                ];
            }
        } else {
            $data = $query->getQuery()->getSingleScalarResult();
        }

        return $data;
    }

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
     * @param QueryBuilder $query
     */
    private function resultsOrder(QueryBuilder $query): void
    {
        switch ($this->orderBy) {
            case 'message':
                $orderBy = 'mm.body';
                break;
            case 'created_at':
                $orderBy = 'mm.createdAt';
                break;
            case 'send_by':
                $query->orderBy('ud.last_name', $this->orderDir)
                      ->addOrderBy('ud.first_name', $this->orderDir);

                return;
            default:
                $orderBy = 'mm.' . $this->orderBy;
        }

        $query->orderBy($orderBy, $this->orderDir);
    }


    /**
     * @param string $orderBy
     */
    public function setOrderBy(string $orderBy): void
    {
        if (! strlen($orderBy)) {
            $orderBy = 'createdAt';
        };

        $this->orderBy = $orderBy;
    }

    /**
     * @param string $orderDir
     */
    public function setOrderDir(string $orderDir): void
    {
        $orderDir = strtoupper($orderDir);

        if (! in_array($orderDir, ['ASC', 'DESC'])) {
            $orderDir = 'DESC';
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
        $this->columnFilter = $columnFilter;
    }

    private function filterByAccount(QueryBuilder $query): void
    {
        if ($this->has('account_id')) {
            $query->andWhere('mm.account = :accountId');
            $query->setParameter('accountId', $this->filters['account_id']);
        }
    }

    /**
     * @param QueryBuilder $query
     */
    private function filterByColumns(QueryBuilder $query): void
    {
        foreach ($this->columnFilter as $column => $value) {
            if (empty($value)) {
                continue;
            }

            switch ($column) {
                case 'send_by':
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
                case 'created_at':
                    $query->andWhere('DATE(mm.createdAt) LIKE :value')->setParameter('value', '%' . $value . '%');
                    break;
            }
        }
    }
}
