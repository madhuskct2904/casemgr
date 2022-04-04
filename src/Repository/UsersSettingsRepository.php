<?php

namespace App\Repository;

use App\Entity\Users;
use App\EntityRepository\EntityRepository;
use App\Entity\UsersSettings;

/**
 * Class UsersSettingsRepository
 *
 * @package App\Repository
 */
class UsersSettingsRepository extends EntityRepository
{
    /**
     * @param Users $user
     * @param string $name
     * @param string $value
     */
    public function save(Users $user, string $name, string $value)
    {
        $em = $this->getEntityManager();

        foreach ($this->findByUser($user) as $row) {
            $em->remove($row);
            $em->flush();
        }

        $settings = new UsersSettings();

        $settings->setUser($user);
        $settings->setName($name);
        $settings->setValue($value);

        $em->persist($settings);
        $em->flush();
    }

    public function deleteFromTopReports(array $ids)
    {
        $ids = array_unique($ids);

        $results = $this->createQueryBuilder('us')
            ->where('us.name LIKE :name')
            ->setParameter('name', '%top_reports_account_%')
            ->getQuery()
            ->getResult();

        foreach ($results as $result) {
            $values = json_decode($result->getValue(), true);

            foreach ($ids as $id) {
                if (($key = array_search($id, $values)) !== false) {
                    unset($values[$key]);
                    $result->setValue(json_encode(array_values($values)));
                    $this->getEntityManager()->flush();
                }
            }
        }
    }
}
