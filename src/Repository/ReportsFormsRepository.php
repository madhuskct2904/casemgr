<?php

namespace App\Repository;

use App\EntityRepository\EntityRepository;

/**
 * Class ReportFormsRepository
 *
 * @package App\Repository
 */
class ReportsFormsRepository extends EntityRepository
{
    public function getInvalidatedForms($reportId)
    {
        return $this->createQueryBuilder('rf')
            ->join('rf.form', 'f')
            ->select('f.id')
            ->where('rf.invalidatedAt IS NOT NULL')
            ->andWhere('rf.report =:report')
            ->setParameter('report', $reportId)
            ->getQuery()
            ->getArrayResult();
    }

    public function setFormsAsValid($reportId)
    {
        return $this->createQueryBuilder('rf')
            ->update()
            ->set('rf.invalidatedAt', 'NULL')
            ->where('rf.report = :report')
            ->setParameter('report', $reportId)
            ->getQuery()
            ->execute();
    }

    public function invalidateForm($form)
    {
        return $this->createQueryBuilder('rf')
            ->update()
            ->set('rf.invalidatedAt', ':now')
            ->setParameter('now', new \DateTime())
            ->where('rf.form = :form')
            ->setParameter('form', $form)
            ->getQuery()
            ->execute();
    }

    public function invalidateForms(array $formsIds)
    {
        return $this->createQueryBuilder('rf')
            ->update()
            ->set('rf.invalidatedAt', ':now')
            ->setParameter('now', new \DateTime())
            ->where('rf.form IN (:forms)')
            ->setParameter('forms', $formsIds)
            ->getQuery()
            ->execute();
    }

    public function invalidateReport($report)
    {
        return $this->createQueryBuilder('rf')
            ->update()
            ->set('rf.invalidatedAt', ':now')
            ->setParameter('now', new \DateTime())
            ->where('rf.report = :report')
            ->setParameter('report', $report)
            ->getQuery()
            ->execute();
    }

    public function removeForm($form)
    {
        return $this->createQueryBuilder('rf')
            ->delete()
            ->where('rf.form = :form')
            ->setParameter('form', $form)
            ->getQuery()
            ->execute();
    }
}
