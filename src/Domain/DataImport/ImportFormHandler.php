<?php

namespace App\Domain\DataImport;

use App\Entity\Forms;
use Doctrine\ORM\EntityManagerInterface;

class ImportFormHandler extends BaseImportHandler
{
    private $form;
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getFields(): array
    {
        $fields = [];

        foreach ($this->getFormFields() as $field) {
            $fields[$field['name']] = $field['description'];
        }

        if (in_array($this->getForm()->getModule()->getKey(), ['activities_services', 'assessment_outcomes'])) {
            $fields['import-completed-date'] = 'Completed date';
        }

        return $fields;
    }

    public function getTemplateCsvHeader(): array
    {
        $columns = array_column($this->getFormFields(), 'description');

        if ($this->getForm()->getModule()->getRole() !== 'profile') {
            $columns[] = 'System ID';
        }

        return $columns;
    }

    public function getTemplateCsvSubHeader(): array
    {
        $columns = array_column($this->getFormFields(), 'label');

        if ($this->getForm()->getModule()->getRole() !== 'profile') {
            $columns[] = 'System ID';
        }

        return $columns;
    }

    public function preValidate(array $csvRows, array $map, bool $ignoreRequired): array
    {
        $participantIdRequired = $this->getForm()->getModule()->getRole() !== 'profile';

        $validator = new ImportFormValidator($this->getFormFields(), $map, $participantIdRequired, $ignoreRequired);

        $exceptionRows = [];

        foreach ($csvRows as $rowIdx => $csvRow) {
            if ($rowIdx === 0) { // skip header
                continue;
            }

            $row = $validator->validateRow($csvRow);

            if (!$row->isValid()) {
                $exceptionRows[] = array_merge(['rowIdx' => $rowIdx], $row->getRow());
            }
        }

        return $exceptionRows;
    }

    public function setForm(Forms $form)
    {
        $this->form = $form;
    }

    public function getFormFields()
    {
        $array = json_decode($this->getForm()->getData(), true);

        foreach ($array as $k => $field) {
            if (!in_array($field['type'], ImportSettings::$importFieldTypes)) {
                unset($array[$k]);
                continue;
            }

            if ($field['type'] === 'accordion') {
                foreach ($field['values'] as $accordionField) {
                    if (!in_array($accordionField['type'], ImportSettings::$importFieldTypes)) {
                        unset($array[$k]);
                        continue;
                    }
                    $array[] = $accordionField;
                }
                unset($array[$k]);
            }
        }

        return $array;
    }

    public function getForm()
    {
        return $this->form;
    }

}
