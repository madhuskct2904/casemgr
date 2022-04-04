<?php

namespace App\Repository;

use App\Entity\Forms;
use App\Entity\SharedField;
use Doctrine\ORM\EntityNotFoundException;

class SharedFieldRepository extends \Doctrine\ORM\EntityRepository
{

    public function removeAllForForm(Forms $form)
    {
        return $this->createQueryBuilder('sf')
            ->where('form = :form')
            ->setParameter('form', $form)
            ->delete();
    }

    public function findForForm(Forms $form)
    {
        $fields = $this->createQueryBuilder('sf')
            ->where('sf.form = :form')
            ->setParameter('form', $form)
            ->getQuery()
            ->getResult();

        $fieldsByName = [];

        /** @var SharedField $field */
        foreach ($fields as $field) {

            $dataRange = null;
            $range = $field->getSourceFormDataRange();

            if ($range && isset($range[0], $range[1])) {

                $dateFormat = isset($range[2]) ? $range[2] : 'm/d/y';

                if ($dateFormat === 'm/d/Y') {
                    $dataRange = [$range[0], $range[1]];
                } else {
                    $dateFrom = \DateTime::createFromFormat('m/d/Y', $range[0]);
                    $dateTo = \DateTime::createFromFormat('m/d/Y', $range[1]);

                    if ($dateFrom && $dateTo) {
                        $dataRange = [$dateFrom->format($dateFormat), $dateTo->format($dateFormat)];
                    }
                }
            }

            if ($field->getSourceForm()){
                try {
                    $fieldsByName[$field->getFieldName()] = [
                        'sourceFormId' => $field->getSourceForm()->getId(),
                        'sourceFieldName' => $field->getSourceFieldName(),
                        'sourceFormData' => $field->getsourceFormData(),
                        'sourceFieldFunction' => $field->getSourceFieldFunction(),
                        'sourceFormDataRange' => $dataRange,
                        'sourceFieldType' => $field->getSourceFieldType(),
                        'sourceFieldValue' => $field->getSourceFieldValue(),
                        'dateRangeField' => $field->getDateRangeField(),
                        'readOnly' => $field->isReadOnly()
                    ];
                } catch (EntityNotFoundException $exception) {
                    // form not exists

                }
            }
        }

        return $fieldsByName;
    }
}
