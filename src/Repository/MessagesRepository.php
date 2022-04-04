<?php

namespace App\Repository;

use App\Entity\Accounts;
use App\Entity\Messages;
use App\Entity\Users;
use App\EntityRepository\EntityRepository;
use App\Enum\ParticipantType;
use DateTimeZone;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\QueryBuilder;

/**
 * Class MessagesRepository
 * @package App\Repository
 */
class MessagesRepository extends EntityRepository
{
    private string $orderBy = 'createdAt';
    private string $orderDir = 'DESC';
    private array $columnFilter = [];
    private array $filters = [];
    private ?DateTimeZone $timezone = null;

    /**
     * @param string $name
     * @param mixed $value
     */
    public function set(string $name, $value): void
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
        $query = $this->createQueryBuilder('m');

        if ($get_count === false) {
            $query->select(
                'm'
            );
        } else {
            $query->select('count(m)');
        }

        $query->leftJoin('App\Entity\Users', 'u', 'WITH', 'm.participant = u.id');
        $query->leftJoin('App\Entity\UsersData', 'ud', 'WITH', 'u.id = ud.user');

        if ($this->has('mass_message')) {
            $query->andWhere('m.massMessage = :mmId');
            $query->setParameter('mmId', $this->filters['mass_message']);
        }

        $query->andWhere('m.status != \'system_administrator\'');

        if ($this->has('participant')) {
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

        /** Get data */
        if ($get_count === false) {
            $results = $this->paginate($query, $this->filters['current_page'], $this->filters['limit']);
            $data = [];


            foreach ($results as $result) {
                $data[] = [
                    'id'              => $result->getId(),
                    'participant'     => $result->getParticipant()->getData()->getFullName(),
                    'system_id'       => $result->getParticipant()->getData()->getSystemId(),
                    'organization_id' => $result->getParticipant()->getData()->getOrganizationId(),
                    'status'          => $result->getStatusTransformed()
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
            case 'system_id':
                $orderBy = 'ud.system_id';
                break;
            case 'organization_id':
                $orderBy = 'ud.organizationId';
                break;
            case 'status':
                $orderBy = 'm.status';
                break;
            case 'participant':
                $query->orderBy('ud.last_name', $this->orderDir)
                    ->addOrderBy('ud.first_name', $this->orderDir);

                return;
            default:
                $orderBy = 'm.' . $this->orderBy;
        }

        $query->orderBy($orderBy, $this->orderDir);
    }


    /**
     * @param string $orderBy
     */
    public function setOrderBy(string $orderBy): void
    {
        if (!strlen($orderBy)) {
            $orderBy = 'createdAt';
        }

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
                case 'participant':
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
                case 'system_id':
                    $query->andWhere('ud.system_id LIKE :columnSystemId')->setParameter('columnSystemId', '%' . $value . '%');
                    break;
                case 'status':
                    $query->andWhere('m.status LIKE :columnStatus')->setParameter('columnStatus', '%' . $value . '%');
                    break;
                case 'organization_id':
                    $query
                        ->andWhere('ud.organizationId LIKE :columnOrganizationId')
                        ->setParameter('columnOrganizationId', '%' . $value . '%');
                    break;
            }
        }
    }


    /**
     * @param Users $participant
     * @param Users $user
     * @param null $search
     *
     * @return Messages[]
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function getByParticipant(Users $participant, Users $user, $search = null): array
    {
        $qb = $this->createQueryBuilder('m');


        if ($search) {
            if ($participant->getUserDataType() == ParticipantType::INDIVIDUAL) {
                $qb
                    ->leftJoin('m.user', 'mu')
                    ->leftJoin('mu.individualData', 'ud');

                $qb->andWhere('LOWER(m.body) LIKE :search');
                $qb->orWhere($qb->expr()->andX(
                    $qb->expr()->orX(
                        $qb->expr()->like(
                            $qb->expr()->concat(
                                'ud.first_name',
                                $qb->expr()->concat($qb->expr()->literal(' '), 'ud.last_name')
                            ),
                            $qb->expr()->literal($search . '%')
                        ),
                        $qb->expr()->like(
                            $qb->expr()->concat(
                                'ud.last_name',
                                $qb->expr()->concat($qb->expr()->literal(' '), 'ud.first_name')
                            ),
                            $qb->expr()->literal($search . '%')
                        )
                    )
                ));
            }

            if ($participant->getUserDataType() == ParticipantType::MEMBER) {
                $qb
                    ->leftJoin('m.user', 'mu')
                    ->leftJoin('mu.memberData', 'ud');

                $qb->andWhere('LOWER(m.body) LIKE :search');
                $qb->orWhere('ud.name LIKE :search');
            }

            $qb->setParameter('search', '%' . strtolower($search) . '%');
        }

        $qb
            ->andWhere('m.participant = :participant')
            ->setParameter('participant', $participant->getId())
            ->andWhere('m.assignment IS NULL');

        $qb->orderBy('m.createdAt', 'asc');

        /** @var Messages[] $messages */
        $messages = $qb->getQuery()->getResult();
        $timezone = $this->timezone;
        $data     = [];

        foreach ($messages as $k => $message) {
            $data[$k]['participant'] = [
                'id'       => $message->getParticipant()->getId(),
                'fullName' => $message->getParticipant()->getData()->getFullName(false),
                'avatar'   => $message->getParticipant()->getData()->getAvatar()
            ];
            if ($message->getUser()) {
                $data[$k]['user'] = [
                    'id'       => $message->getUser()->getId(),
                    'fullName' => $message->getUser()->getData()->getFullName(false),
                    'avatar'   => $message->getUser()->getData()->getAvatar()
                ];
            } else {
                $data[$k]['user'] = null;
            }

            $createdAt = $message->getCreatedAt();

            if (null !== $timezone) {
                $createdAt->setTimezone($timezone);
            }

            $data[$k]['createdAt'] = $createdAt->format('m/d/Y h:i A');
            $data[$k]['body']      = $message->getBody();
            $data[$k]['type']      = $message->getType();
            $data[$k]['status']    = $message->getStatus();
            $data[$k]['error']     = $message->getError();

            // clear user - inbound messages from this participant to this user
            $em = $this->getEntityManager();
            if ($message->getType() === 'inbound' && $message->getUser() && $message->getUser()->getId() === $user->getId()) {
                $message->setUser(null);
                $em->flush();
            }
            if ($message->getType() === 'inbound' && $message->getCaseManagerSecondary() && $message->getCaseManagerSecondary()->getId() === $user->getId()) {
                $message->setCaseManagerSecondary(null);
                $em->flush();
            }
        }

        return $data;
    }

    public function getAlerts(Users $user, Accounts $account): array
    {
        $qb = $this->createQueryBuilder('m');

        $qb
            ->andWhere('m.type = :inbound')
            ->setParameter('inbound', 'inbound')
            ->andWhere('m.user = :user OR m.case_manager_secondary = :user')
            ->setParameter('user', $user->getId())
            ->leftJoin('m.participant', 'p')
            ->innerJoin('p.accounts', 'a', 'WITH', 'a.id = :account')
            ->setParameter('account', $account->getId())
            ->andWhere('m.assignment IS NULL')
            ->orderBy('m.createdAt', 'desc');

        /** @var Messages[] $messages */
        $messages = $qb->getQuery()->getResult();
        $timezone = $this->timezone;
        $data     = [];

        foreach ($messages as $message) {
            $createdAt = $message->getCreatedAt();

            if (null !== $timezone) {
                $createdAt->setTimezone($timezone);
            }

            $data[] = [
                'participantId' => $message->getParticipant()->getId(),
                'fullName'      => $message->getParticipant()->getData()->getFullName(false),
                'avatar'        => $message->getParticipant()->getData()->getAvatar(),
                'createdAt'     => $createdAt->format('m/d/Y h:i A'),
            ];
        }

        return $data;
    }

    /**
     * @param $phone
     * @param $aPhone
     *
     * @return mixed
     * @throws NonUniqueResultException
     */
    public function getParticipantByPhone($phone, $aPhone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $aPhone = preg_replace('/[^0-9]/', '', $aPhone);

        $individual = $this->findInIndividuals($phone, $aPhone);

        if ($individual) {
            return $individual;
        }

        return $this->findInMembers($phone, $aPhone);
    }

    /**
     * @param $phone
     * @param $aPhone
     * @return mixed
     */
    private function findInIndividuals($phone, $aPhone)
    {
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select(['u', 'ud'])
            ->from('App\Entity\Users', 'u')
            ->innerJoin('u.individualData', 'ud')
            ->andWhere('u.type = :type')
            ->setParameter('type', 'participant')
            ->andWhere('ud.phone_number = :shortphone OR ud.phone_number = :phone')
            ->setParameter('shortphone', $phone)
            ->setParameter('phone', '+' . $phone)
            ->setMaxResults(1)
            ->innerJoin('u.accounts', 'ua')
            ->andWhere('ua.twilioPhone = :ashortphone OR ua.twilioPhone = :aphone')
            ->setParameter('ashortphone', $aPhone)
            ->setParameter('aphone', '+' . $aPhone)
            ->getQuery()
            ->getOneOrNullResult();
    }


    /**
     * @param $phone
     * @param $aPhone
     * @return mixed
     */
    private function findInMembers($phone, $aPhone)
    {
        return $this
            ->getEntityManager()
            ->createQueryBuilder()
            ->select(['u', 'ud'])
            ->from('App\Entity\Users', 'u')
            ->innerJoin('u.memberData', 'ud')
            ->andWhere('u.type = :type')
            ->setParameter('type', 'participant')
            ->andWhere('ud.phone_number = :shortphone OR ud.phone_number = :phone')
            ->setParameter('shortphone', $phone)
            ->setParameter('phone', '+' . $phone)
            ->setMaxResults(1)
            ->innerJoin('u.accounts', 'ua')
            ->andWhere('ua.twilioPhone = :ashortphone OR ua.twilioPhone = :aphone')
            ->setParameter('ashortphone', $aPhone)
            ->setParameter('aphone', '+' . $aPhone)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
