<?php

namespace App\Service;

use App\Entity\Forms;
use Doctrine\ORM\EntityManagerInterface;

/** Update existing form values if values for dropdowns, radio groups or checkboxes groups changed in form builder */

class FormBuilderService
{
    protected $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;
    }

    public function updateExistingFormsData(Forms $form, array $oldDataArr, array $newDataArr)
    {
        // form unmodified, nothing to do
        if ($oldDataArr == $newDataArr) {
            return;
        }

        foreach ($oldDataArr as $idx => $field) {
            if (!isset($field['type'])) {
                continue;
            }

            if (in_array($field['type'], ['checkbox-group', 'radio-group', 'select'])) {
                $oldValues = $field['values'];
                $newValues = $this->findValuesByName($field['name'], $newDataArr);

                if ($oldValues == $newValues) {
                    // nothing changed, nothing to do
                    continue;
                }

                if ($newValues == null) {
                    // field removed, nothing to do
                    continue;
                }

                foreach ($oldValues as $oldValue) {

                    if (!isset($oldValue['id'])) {
                        continue;
                    }

                    $newLabel = $this->findLabelById($oldValue['id'], $newValues);

                    if ($oldValue['label'] == $newLabel) {
                        continue;
                    }

                    $matchedFields = $this->em->getRepository('App:FormsValues')->findFieldByFormNameAndValue($form, $field['name'], $oldValue['label']);

                    if ($newLabel === null) {
                        continue;
                    }

                    $this->updateFieldsValue($matchedFields, $newLabel);
                }

                continue;
            }

            if ($field['type'] == 'checkbox') {
                $newLabel = $field['label'];
                $oldLabel = $this->findLabelByName($field['name'], $oldDataArr);

                if ($oldLabel == $newLabel) {
                    continue;
                }

                $matchedFields = $this->em->getRepository('App:FormsValues')->findFieldByFormNameAndValue($field['name'], $oldLabel);
                $this->updateFieldsValue($matchedFields, $newLabel);
            }
        }

        $this->em->getRepository('App:ReportsForms')->invalidateForm($form);
    }

    private function updateFieldsValue($fields, string $value): void
    {
        foreach ($fields as $field) {
            $field->setValue($value);
        }

        $this->em->flush();
    }

    private function removeFields($fields): void
    {
        foreach ($fields as $field) {
            $this->em->remove($field);
        }

        $this->em->flush();
    }

    private function findValuesByName(string $name, array $dataArr): ?array
    {
        foreach ($dataArr as $field) {
            if (isset($field['name']) && $field['name'] == $name && isset($field['values'])) {
                return $field['values'];
            }
        }

        return null;
    }


    private function findLabelById(string $id, array $values): ?string
    {
        foreach ($values as $idx => $value) {
            if (!$value['id']) {
                continue;
            }

            if ($value['id'] == $id) {
                return $value['label'];
            }
        }

        return null;
    }

    private function findLabelByName(string $name, array $dataArr): ?string
    {
        foreach ($dataArr as $field) {
            if (isset($field['name']) && $field['name'] == $name) {
                return $field['label'];
            }
        }

        return null;
    }
}
