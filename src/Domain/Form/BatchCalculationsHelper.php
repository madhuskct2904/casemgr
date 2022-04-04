<?php

namespace App\Domain\Form;

use App\Entity\Forms;
use App\Service\FormCalculations;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class BatchCalculationsHelper
 *
 * Calculate given FormData entries
 */
class BatchCalculationsHelper
{
    private $em;
    private $formCalculations;

    public function __construct(EntityManagerInterface $em, FormCalculations $formCalculations)
    {
        $this->em = $em;
        $this->formCalculations = $formCalculations;
    }

    public function recalculateFormsDataForForm(Forms $form): void
    {
        $calculations = json_decode($form->getCalculations(), true);

        if (!count($calculations)) {
            return;
        }

        $this->formCalculations->setCalculations($calculations);
        $fields = $this->formDataFields($form);

        $this->formCalculations->setFields($fields);

        $formId = $form->getId();

        $conn = $this->em->getConnection();
        $result = $conn->fetchAllAssociative("SELECT fv.name, fv.value, fd.id FROM forms_values fv JOIN forms_data fd ON fv.data_id = fd.id WHERE fd.form_id = $formId");

        $map = [];

        foreach ($result as $res) {
            $map[$res['id']][$res['name']] = $res['value'];
        }

        foreach ($map as $formDataId => $formDataValues) {
            $this->formCalculations->setData($formDataValues);
            $calculationResult = $this->formCalculations->calculate();
            $this->updateFormDataValues($formDataId, $calculationResult, $formDataValues);
        }
    }

    public function recalculateFormsData(array $formsData)
    {
        foreach ($formsData as $formData) {
            $dataId = $formData->getId();

            $formDataValues = $this->mapFormDataValues($dataId);

            $form = $formData->getForm();

            $calculations = json_decode($form->getCalculations(), true);

            if (!count($calculations)) {
                continue;
            }

            $this->formCalculations->setCalculations($calculations);
            $fields = $this->formDataFields($form);

            $this->formCalculations->setFields($fields);
            $this->formCalculations->setData($formDataValues);

            $calculationResult = $this->formCalculations->calculate();

            $this->updateFormDataValues($dataId, $calculationResult, $formDataValues);
        }
    }

    private function formDataFields(Forms $form): array
    {
        $array = json_decode($form->getData(), true);

        // accordion values as fields
        foreach ($array as $k => $field) {
            if ($field['type'] === 'accordion') {
                foreach ($field['values'] as $accordionField) {
                    array_push($array, $accordionField);
                }
                unset($array[$k]);
            }
        }

        return $array;
    }

    protected function mapFormDataValues(int $dataId): array
    {
        $conn = $this->em->getConnection();
        $values = $conn->fetchAllAssociative("SELECT name, value FROM forms_values WHERE data_id = $dataId");

        $formValues = [];

        foreach ($values as $value) {
            $formValues[$value['name']] = $value['value'];
        }
        return $formValues;
    }

    protected function updateFormDataValues($dataId, array $calculationResult, array $formDataValues): void
    {
        $conn = $this->em->getConnection();

        $updateValues = array_diff_assoc($calculationResult, $formDataValues);

        foreach ($updateValues as $name => $newValue) {
            try {
                $conn->exec("UPDATE forms_values SET `value` = '$newValue' WHERE data_id = $dataId AND `name` = '$name'");
            } catch (\Exception $e) {

            }
        }
    }
}
