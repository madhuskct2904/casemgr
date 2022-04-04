<?php

namespace App\Repository;

use App\Entity\Accounts;
use App\Entity\FormsData;
use App\Entity\FormsValues;
use App\Entity\Modules;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;

/**
 * Class FormsValuesRepository
 *
 * @package App\Repository
 */
class FormsValuesRepository extends EntityRepository
{
    public function findDisplayNameByFileName(string $file_name): ?string
    {
        $query = $this->createQueryBuilder('v');

        $query->select('v');
        $query
            ->where(
                $query->expr()->like(
                    'v.value',
                    $query->expr()->literal('%"' . $file_name . '"%')
                )
            );
        $query->setMaxResults(1);

        $find = $query->getQuery()->getSingleResult();

        if ($find === null) {
            return null;
        }

        $value = json_decode($find->getValue(), true);

        foreach ($value as $row) {
            if ($row['file'] === $file_name) {
                return $row['name'];
            }
        }

        return null;
    }

    public function findFieldByFormNameAndValue($form, $name, $value)
    {
        $qb = $this->createQueryBuilder('v');
        return $qb->where('v.name LIKE :name')
            ->innerJoin('v.data', 'fd', 'WITH', 'v.data = fd')
            ->innerJoin('fd.form', 'f', 'WITH', 'fd.form = :form')
            ->setParameter('name', $name . '%')
            ->andWhere('v.value =:value')
            ->setParameter('value', $value)
            ->andWhere('f = :form')
            ->setParameter('form', $form)
            ->getQuery()
            ->getResult();
    }

    public function findUserByAccountFieldNameAndValue($field, $value, $account, $profileForm, $elementIds)
    {
        $qb = $this->createQueryBuilder('v');

        return $qb->select('fd.element_id')
            ->distinct()
            ->where('v.name LIKE :name')
            ->setParameter('name', $field . '%')
            ->innerJoin('v.data', 'fd', 'WITH', $qb->expr()->andX(
                $qb->expr()->eq('v.data', 'fd'),
                $qb->expr()->in('fd.element_id', $elementIds),
                $qb->expr()->eq('fd.account_id', $account->getId()),
                $qb->expr()->eq('fd.form', $profileForm->getId())

            ))
            ->andWhere('v.value =:value')
            ->setParameter('value', $value)
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);
    }

    public function findByNamesNotInData($names, $notInData)
    {
        $qb = $this->createQueryBuilder('fv');
        return $qb->where('fv.name IN (:names)')
            ->setParameter('names', $names)
            ->andWhere('fv.data NOT IN (:data)')
            ->setParameter('data', $notInData)
            ->getQuery()
            ->getResult();
    }

    public function findByNamesInData(array $names, FormsData $data)
    {
        $qb = $this->createQueryBuilder('fv')
            ->where('fv.data IN (:data)')
            ->setParameter('data', $data);

        $checkboxesGroups = [];

        foreach ($names as $name) {
            if (strpos($name, 'checkbox-group-') !== false) {
                $checkboxesGroups[] = substr($name, 0, strrpos($name, '-'));
            }
        }

        if (count($checkboxesGroups)) {
            $checkboxesGroups = array_unique($checkboxesGroups);
            $orStatements = $qb->expr()->orX();

            $orStatements->add($qb->expr()->in('fv.name', $names));

            foreach ($checkboxesGroups as $checkboxesGroupField) {
                $orStatements->add($qb->expr()->like('fv.name', $qb->expr()->literal($checkboxesGroupField . '%')));
            }
            $qb->andWhere($orStatements);
        } else {
            $qb->andWhere('fv.name IN (:names)')
                ->setParameter('names', $names);
        }

        return $qb->getQuery()->getResult();
    }

    public function findFileByOptNameAndFilename($optname, $filename)
    {

        return $this->createQueryBuilder('fv')
            ->where('fv.name = :optname')
            ->andWhere('fv.value LIKE :value')
            ->setParameter('optname', $optname)
            ->setParameter('value', '%"file":"' . $filename . '"%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByFileName($fileName)
    {
        return $this->createQueryBuilder('fv')
            ->where("fv.name LIKE 'file-%'")
            ->where('fv.value LIKE :value')
            ->setParameter('value', '%"file":"' . $fileName . '"%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByNameAndDataIds(string $name, array $dataIds)
    {
        $result = $this->createQueryBuilder('fv')
            ->select('fv.value')
            ->where('fv.data IN (:dataIds)')
            ->andWhere('fv.name LIKE :name')
            ->setParameter('name', $name.'%')
            ->setParameter('dataIds', $dataIds)
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'value');
    }

    public function findByNameValueAndDataIds(string $name, string $value, array $dataIds)
    {
        $result = $this->createQueryBuilder('fv')
            ->select('count(fv.id)')
            ->where('fv.data IN (:dataIds)')
            ->andWhere('fv.name LIKE :name')
            ->andWhere('fv.value = :value')
            ->setParameter('name', $name.'%')
            ->setParameter('dataIds', $dataIds)
            ->setParameter('value', $value)
            ->getQuery()
            ->getSingleScalarResult();
        return $result;
    }

    public function updateByNameAndDataIds(?string $value, string $name, array $dataIds)
    {
        $this->createQueryBuilder('fv')
            ->update()
            ->set('fv.value', ':value')
            ->setParameter('value', $value)
            ->where('fv.name = :fieldName')
            ->setParameter('fieldName', $name)
            ->andWhere('fv.data IN (:dataIds)')
            ->setParameter('dataIds', $dataIds)
            ->getQuery()
            ->execute();
    }

    public function findByNameAndDataElementId(string $name, int $elementId): ?FormsValues
    {
        return $this->createQueryBuilder('fv')
            ->leftJoin('fv.data', 'data')
            ->where('fv.name = :fieldName')
            ->andWhere('data.element_id = :elementId')
            ->orderBy('fv.id', 'DESC')
            ->setParameter('fieldName', $name)
            ->setParameter('elementId', $elementId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
