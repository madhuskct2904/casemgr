<?php

namespace App\Domain\FormData;

use App\Domain\Form\FormSchemaHelper;
use App\Domain\FormValues\FormValuesParser;
use App\Domain\FormValues\FormValuesParserException;
use App\Entity\Forms;
use App\Entity\FormsData;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Prepare forms data preview for frontend in format compatible with VueGoodTable (use getColumns() and getRows() methods)
 */
final class FormDataTableHelper
{
    protected $form;
    protected $completedForms;
    protected EntityManagerInterface $entityManager;
    protected $formHelper;
    protected $formValuesParser;
    protected $dateFormat = 'm/d/Y';
    private $formColumns;

    protected $columnsFilter = false;

    public function __construct(
        EntityManagerInterface $entityManager,
        FormSchemaHelper $formHelper,
        FormValuesParser $formValuesParser
    ) {
        $this->entityManager = $entityManager;
        $this->formHelper = $formHelper;
        $this->formValuesParser = $formValuesParser;
    }

    public function setForm(Forms $form): void
    {
        $this->form = $form;
        $this->formHelper->setForm($form);
        $this->formColumns = $this->formHelper->getFlattenColumns();
        $this->columnsFilter = false;
    }

    /**
     * Get only selected columns, eg. $filter = ['text-1234568','select-987654321'].
     */
    public function setColumnsFilter(array $filter): void
    {
        $this->columnsFilter = $filter;
    }

    /**
     * Set non-default date format if necessary
     */
    public function setDateFormat(string $format): void
    {
        $this->dateFormat = $format;
    }

    public function setFormDataEntries(array $completedForms): void
    {
        $this->completedForms = $completedForms;
    }

    public function getColumns(): array
    {
        if (!$this->form) {
            throw new CompletedFormsTableException('Form not set!');
        }

        $allColumns = $this->prepareColumns($this->formColumns);

        if (!$this->columnsFilter) {
            return $allColumns;
        }

        return array_values(array_filter($allColumns, function ($item) {
            return in_array($item['field'], $this->columnsFilter);
        }));

    }

    public function getRows(): array
    {
        if (!$this->completedForms) {
            throw new CompletedFormsTableException('Completed forms not set!');
        }

        return $this->prepareRows($this->formColumns, $this->completedForms);
    }

    private function prepareColumns(array $formColumns): array
    {
        $columns = [];
        $colIndex = 0;

        foreach ($formColumns as $column) {
            if (in_array($column['type'], ['header', 'divider', 'row', 'text-entry'])) {
                continue;
            }

            $existsHideInPreview = array_key_exists('hideInPreview', $column);

            if (true === $existsHideInPreview && true === (bool) $column['hideInPreview']) {
                continue;
            }

            $columns[$colIndex]['field'] = $column['name'];
            $columns[$colIndex]['label'] = $column['description'];

            if (in_array($column['type'], ['checkbox-group'])) {
                $columns[$colIndex]['html'] = true;
            }

            $colIndex++;
        }


        $columns[] = ['field' => '_date_created', 'label' => 'Date Created'];
        $columns[] = ['field' => '_date_modified', 'label' => 'Date Modified'];

        return array_values($columns);
    }

    private function prepareRows(array $formColumns, array $completedForms): array
    {
        $rows = [];

        foreach ($completedForms as $completedForm) {
            $rows[] = $this->prepareFormRow($formColumns, $completedForm);
        }

        return $rows;
    }


    private function prepareFormRow(array $formColumns, FormsData $completedForm): array
    {
        $formValues = $completedForm->getValues();
        $this->formValuesParser->setCompletedFormData($completedForm);
        $this->formValuesParser->setDateFormat($this->dateFormat);

        if (!$formValues) {
            return [];
        }

        $row = ['id' => $completedForm->getId()];

        foreach ($formColumns as $formColumn) {

            if ($this->columnsFilter && !in_array($formColumn['name'], $this->columnsFilter)) {
                continue;
            }

            $existsHideInPreview = array_key_exists('hideInPreview', $formColumn);

            if (true === $existsHideInPreview && true === (bool) $formColumn['hideInPreview']) {
                continue;
            }

            try {
                $row[$formColumn['name']] = $this->formValuesParser->getFieldValue($formColumn, ', ');
            } catch (FormValuesParserException $e) {
                throw new FormDataTableException('Something went wrong while getting column value: ' . $e->getMessage());
            }
        }

        $createdEqualsModified = $completedForm->getCreatedDate()->getTimestamp() === $completedForm->getUpdatedDate()->getTimestamp();

        $row ['_date_created'] = $completedForm->getCreatedDate();
        $row ['_date_modified'] = $createdEqualsModified ? null : $completedForm->getUpdatedDate();

        return $row;
    }
}
