<?php

namespace App\Repository;

/**
 * WorkspaceSharedFileRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class WorkspaceSharedFileRepository extends \Doctrine\ORM\EntityRepository
{
    public function search($account, $offset = null, $limit = null, $filters = null, $sort, $getDeleted = false, $countOnly = false)
    {
        $qb = $this->createQueryBuilder('f');

        if ($countOnly) {
            $qb->select('count(f.id)');
        }

        $qb->where('f.account =:account')
            ->setParameter('account', $account);

        if (!$countOnly && $offset) {
            $qb->setFirstResult($offset);
        }

        if (!$countOnly && $limit) {
            $qb->setMaxResults($limit);
        }


        if (is_array($filters)) {
            foreach ($filters as $column => $value) {
                if (empty($value)) {
                    continue;
                }

                switch ($column) {
                    case 'original_filename':
                        {
                            $qb->andWhere('f.originalFilename LIKE :original_filename')
                                ->setParameter('original_filename', '%'.$value.'%');
                            break;
                        }
                    case 'description':
                        {
                            $qb->andWhere('f.description LIKE :description')
                                ->setParameter('description', '%'.$value.'%');
                            break;
                        }
                    case 'username':
                        {
                            $qb
                                ->leftJoin('f.user', 'u')
                                ->leftJoin('u.individualData', 'ud');

                            $qb->andWhere($qb->expr()->andX(
                                $qb->expr()->orX(
                                    $qb->expr()->like(
                                        $qb->expr()->concat(
                                            'ud.first_name',
                                            $qb->expr()->concat($qb->expr()->literal(' '), 'ud.last_name')
                                        ),
                                        $qb->expr()->literal('%' . $value . '%')
                                    ),
                                    $qb->expr()->like(
                                        $qb->expr()->concat(
                                            'ud.last_name',
                                            $qb->expr()->concat($qb->expr()->literal(' '), 'ud.first_name')
                                        ),
                                        $qb->expr()->literal('%' . $value . '%')
                                    )
                                )
                            ));

                            break;
                        }
                    case 'date_attached':
                        {
                            $dateTime = \DateTime::createFromFormat('m/d/Y', $value);

                            if (!$dateTime) {
                                break;
                            }

                            $qb->andWhere('f.createdAt LIKE :date')
                                ->setParameter('date', $dateTime->format('Y-m-d').'%');
                            break;
                        }
                    case 'deleted':
                        {
                            $published = strstr('published', strtolower($value)) !== false;
                            $deleted = strstr('deleted', strtolower($value)) !== false;

                            if ($published) {
                                $qb->andWhere('f.deletedAt IS NULL');
                            }

                            if ($deleted) {
                                $qb->andWhere('f.deletedAt IS NOT NULL');
                            }

                            break;
                        }
                }
            }
        }

        if (is_array($sort) && isset($sort['field']) && $sort['field'] != '') {
            $field = isset($sort['field']) ? $sort['field'] : 'original_filename';

            $fields = [
                'original_filename' => 'f.originalFilename',
                'date_attached'     => 'f.createdAt',
                'description'       => 'f.description',
                'username'          => 'ud.last_name',
                'deleted'           => 'f.deletedAt'
            ];

            if (isset($fields[$field])) {
                $qb
                    ->leftJoin('f.user', 'u')
                    ->leftJoin('u.individualData', 'ud');

                $type = isset($sort['type']) ? $sort['type'] : 'ASC';

                $qb->orderBy($fields[$field], $type);
            }
        }

        if (!$getDeleted) {
            $qb->andWhere('f.deletedAt IS NULL');
        }

        if ($countOnly) {
            return $qb->getQuery()->getSingleScalarResult();
        }

        $results = $qb->getQuery()->getResult();

        $data = [];

        foreach ($results as $result) {
            $data[] = [
                'id'                => $result->getId(),
                'original_filename' => $result->getOriginalFilename(),
                'description'       => $result->getDescription(),
                'status'            => $result->getDeletedAt(),
                'username'          => $result->getUser()->getData()->getLastname() . ', ' . $result->getUser()->getData()->getFirstName(),
                'date_attached'     => $result->getCreatedAt(),
                'deleted'           => $result->getDeletedAt() ? true : false
            ];
        }

        return $data;
    }

    public function countAll()
    {
        return $this->createQueryBuilder('f')->select('count(f.id)')->getQuery()->getSingleScalarResult();
    }

    public function findDeletedMoreThanXDaysAgo(int $days)
    {
        $date = new \DateTime();
        $date->modify('-'.$days.' day');

        return $this
            ->createQueryBuilder('f')
            ->andWhere('f.deletedAt < :date')
            ->setParameter(':date', $date)
            ->getQuery()
            ->execute();
    }

    public function deleteDeletedMoreThanXDaysAgo(int $days)
    {
        $date = new \DateTime();
        $date->modify('-'.$days.' day');

        return $this->createQueryBuilder('f')
            ->delete()
            ->andWhere('f.deletedAt < :date')
            ->setParameter(':date', $date)
            ->getQuery()
            ->execute();
    }
}