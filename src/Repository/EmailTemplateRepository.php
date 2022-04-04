<?php

namespace App\Repository;

use Doctrine\ORM\AbstractQuery;

class EmailTemplateRepository extends \Doctrine\ORM\EntityRepository
{
    public function findOneAsArray($templateId)
    {
        $template = $this->createQueryBuilder('t')
            ->select('t.id, t.name, t.subject, t.header, t.body, t.sender')
            ->where('t =:templateId')
            ->setParameter('templateId', $templateId)
            ->getQuery()
            ->getSingleResult(AbstractQuery::HYDRATE_ARRAY);

        return $template;
    }
}
