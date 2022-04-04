<?php

namespace App\Service\Forms;

use App\Entity\FormsData;
use Doctrine\ORM\EntityManagerInterface;

final class FormDataValuesService
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getRawValues(FormsData $formData): array
    {
        $values = [];

        foreach ($formData->getValues() as $value) {
            $values[$value->getName()] = $value->getValue();
        }

        return $values;
    }

    public function getValuesForParticipantSubmission(FormsData $formData): array
    {
        $values = [];

        foreach ($formData->getValues() as $value) {

            if ((strpos($value->getName(), 'select_case_manager_secondary') === 0) || (strpos($value->getName(), 'select2') === 0)) {

                $user = $this->em->getRepository('App:Users')->findOneBy([
                    'type' => 'user',
                    'id'   => $value->getValue()
                ]);

                $values[$value->getName()] = $user ? $user->getData()->getFullName(false) : '';
                continue;
            }

            $values[$value->getName()] = $value->getValue();
        }

        return $values;
    }


}
