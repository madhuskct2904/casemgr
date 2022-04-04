<?php

namespace App\Domain\FormValues;

use App\Domain\Form\FormSchemaHelper;
use App\Domain\Form\SharedFormPreviewsHelper;
use App\Domain\FormData\FormDataTableException;
use App\Entity\Forms;
use App\Entity\FormsData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Parse form values for frontend
 */
final class FormValuesParser
{
    private FormsData $completedForm;
    private array $formValuesArr;
    private EntityManagerInterface $entityManager;
    private string $dateFormat = 'm/d/Y';
    private ContainerInterface $container;
    private FormSchemaHelper $formSchemaHelper;

    public function __construct(
        EntityManagerInterface $entityManager,
        ContainerInterface $container,
        FormSchemaHelper $formSchemaHelper
    )
    {
        $this->entityManager = $entityManager;
        $this->container = $container;
        $this->formSchemaHelper = $formSchemaHelper;
    }

    public function setCompletedFormData(FormsData $formData): void
    {
        $this->completedForm = $formData;
        $this->formValuesArr = [];

        foreach ($this->completedForm->getValues() as $formValue) {
            $this->formValuesArr[$formValue->getName()] = $formValue->getValue();
        }
    }

    public function setDateFormat(string $format): void
    {
        $this->dateFormat = $format;
    }

    public function getFieldValue(array $formColumn, string $subValueSeparator = '<br/>')
    {
        if (!$this->completedForm) {
            throw new FormValuesParserException('Completed form data not set! Can\'t get value.');
        }

        if (empty($formColumn)) {
            throw new FormValuesParserException('Wrong column array. Can\'t get value.');
        }

        if (!isset($formColumn['name'])) {
            return '';
        }

        $columnName = $formColumn['name'];
        $columnType = $formColumn['displayType'] ?? $formColumn['type'];

        switch ($columnType) {
            case 'checkbox-group':
                $value = '';
                foreach ($this->formValuesArr as $k => $v) {
                    if (strstr($k, $formColumn['name'])) {
                        $value .= $v . $subValueSeparator;
                    }
                }

                return $value = substr($value, 0, strrpos($value, $subValueSeparator));

            case 'signature':
                return empty($this->formValuesArr[$columnName]) ? '' : 'Signed';

            case 'rating':
                if (!isset($this->formValuesArr[$columnName])) {
                    return '';
                }

                return $this->prepareRatingFieldValue($formColumn, $this->formValuesArr[$columnName]);

            case 'password':
                return '***';

            case 'file':
                if (!isset($this->formValuesArr[$columnName])) {
                    return '';
                }

                return $this->prepareFileFieldValue($this->formValuesArr[$columnName]);

            case 'select2':
                if (!isset($this->formValuesArr[$columnName])) {
                    return '';
                }

                $manager = $this->entityManager->getRepository('App:UsersData')->findOneBy([
                    'user' => (int)$this->formValuesArr[$columnName],
                ]);

                return $manager ? $manager->getFullName() : '';

            case 'date':
                if (!isset($this->formValuesArr[$columnName])) {
                    return '';
                }

                $val = $this->formValuesArr[$columnName];

                if ($this->dateFormat != 'm/d/Y') {
                    $date = \DateTime::createFromFormat('m/d/Y', $val);
                    if ($date) {
                        return $date->format($this->dateFormat);
                    }
                }

                return $val;

            case 'json':
                $val = json_decode($this->formValuesArr[$columnName], true);
                $return = '';

                if (json_last_error() === JSON_ERROR_NONE) {
                    foreach ($val as $opt => $value) {
                        $return .= $opt . ': ' . $value . '<br/>';
                    }
                }
                return $return;

            case 'shared-form-preview':
                return $this->prepareSharedFormPreviewValue($this->completedForm->getForm()->getId(), $columnName, $formColumn);

            default:
                if (isset($this->formValuesArr[$columnName])) {
                    return $this->formValuesArr[$columnName];
                }

                return '';
        }
    }

    public function getFieldRawValue(array $formColumn): string
    {
        if (!$this->completedForm) {
            throw new FormValuesParserException('Completed form data not set! Can\'t get value.');
        }

        if (empty($formColumn)) {
            throw new FormValuesParserException('Wrong column array. Can\'t get value.');
        }

        if (!isset($formColumn['name'])) {
            return '';
        }

        $columnName = $formColumn['name'];

        return $this->formValuesArr[$columnName] ?? '';
    }

    private function prepareFileFieldValue(string $formValue): string
    {
        $filesData = json_decode($formValue);

        if ((json_last_error() !== JSON_ERROR_NONE) || !is_array($filesData) || !count($filesData)) {
            return '';
        }

        $value = '';

        foreach ($filesData as $fileData) {
            $value .= $fileData->name . ',';
        }

        return rtrim($value, ',');
    }

    private function prepareRatingFieldValue(array $ratingColumn, string $formValue): string
    {
        return $ratingColumn['values'][array_search(
            $formValue,
            array_column($ratingColumn['values'], 'value')
        )]['label'];
    }

    private function prepareSharedFormPreviewValue(int $formId, string $fieldName, array $formColumn): array
    {
        /** @var Forms $form */
        $formForPreview = $this->entityManager->getRepository('App:Forms')->find($formColumn['formId']);
        $formDataRepository = $this->entityManager->getRepository('App:FormsData');

        $formsData = null;

        $participantUserId = $this->completedForm->getElementId();
        $account = $formForPreview->getAccounts()->first();
        $assignmentId = null;

        if ($formColumn['formData'] === 'daterange-field' && isset($formColumn['formDataRange'][0], $formColumn['formDataRange'][1]) && $formColumn['dateRangeField']) {

            $dateFrom = \DateTime::createFromFormat('m/d/Y', $formColumn['formDataRange'][0]);
            $dateTo = \DateTime::createFromFormat('m/d/Y', $formColumn['formDataRange'][1]);

            $dateFieldName = $formColumn['dateRangeField'];

            $formsDataIds = $formDataRepository->findForParticipantAssignmentAccountFieldDateRange(
                $participantUserId,
                $assignmentId,
                $account,
                $formForPreview,
                $dateFrom,
                $dateTo,
                $dateFieldName
            );

            $formsData = $formDataRepository->findBy(['id' => $formsDataIds]);
        }

        if ($formColumn['formData'] === 'daterange' && isset($formColumn['formDataRange'][0], $formColumn['formDataRange'][1])) {
            $dateFrom = \DateTime::createFromFormat('m/d/Y', $formColumn['formDataRange'][0]);
            $dateTo = \DateTime::createFromFormat('m/d/Y', $formColumn['formDataRange'][1]);

            if ($dateFrom && $dateTo) {
                $formsData = $formDataRepository->findForParticipantAssignmentAccountDateRange(
                    $participantUserId,
                    $assignmentId,
                    $account,
                    $formForPreview,
                    $dateFrom,
                    $dateTo
                );
            }
        }

        if ($formColumn['formData'] === 'all') {
            $formsData = $formDataRepository->findBy([
                'element_id' => $participantUserId,
                'account_id' => $account,
                'assignment' => $assignmentId,
                'form'       => $formForPreview
            ]);
        }

        if ($formColumn['formData'] === 'latest') {
            $formsData = $formDataRepository->findOneBy([
                'element_id' => $participantUserId,
                'account_id' => $account,
                'assignment' => $assignmentId,
                'form'       => $formForPreview
            ], ['created_date' => 'DESC']);
        }

        $columns = $this->prepareColumns($this->formSchemaHelper->getFlattenColumnsForForm($formForPreview));
        $columns = array_values(array_filter($columns, static function ($item) use ($formColumn) {
            return in_array($item['field'], $formColumn['showFields']);
        }));

        $rows = [];

        if ($formsData) {
            $rows = $this->prepareRows($this->formSchemaHelper->getFlattenColumnsForForm($formForPreview), $formsData, $columns);
        }

        $formPreviews[$formColumn['name']] = [
            'columns' => $columns,
            'rows'    => $rows,
            'name'    => $formForPreview->getName()
        ];

        $sharedForm = $formPreviews;

        $data = [];

        foreach ($sharedForm[$fieldName]['rows'] as $row) {
            $item = [];
            foreach ($sharedForm[$fieldName]['columns'] as $column) {
                $item[$column['label']] = $row[$column['field']];
            }
            $data[] = $item;
        }

        return $data;
    }

    private function prepareRows(array $formColumns, array $completedForms, array $columnsFilter): array
    {
        $rows = [];

        foreach ($completedForms as $completedForm) {
            $rows[] = $this->prepareFormRow($formColumns, $completedForm, $columnsFilter);
        }

        return $rows;
    }


    private function prepareFormRow(array $formColumns, FormsData $completedForm, ?array $columnsFilter = null): array
    {
        $formValues = $completedForm->getValues();
        foreach ($formValues as $formValue) {
            $formValuesArr[$formValue->getName()] = $formValue->getValue();
        }

        if (!$formValues) {
            return [];
        }

        $row = ['id' => $completedForm->getId()];

        $filters = [];
        if ($columnsFilter) {
            foreach ($columnsFilter as $columnFilter) {
                $filters[] = $columnFilter['field'];
            }
        }

        foreach ($formColumns as $formColumn) {
            if ($columnsFilter && !in_array($formColumn['name'], $filters)) {
                continue;
            }

            try {
                $row[$formColumn['name']] = $formValuesArr[$formColumn['name']];
            } catch (FormValuesParserException $e) {
                throw new FormDataTableException('Something went wrong while getting column value: ' . $e->getMessage());
            }
        }

        $createdEqualsModified = $completedForm->getCreatedDate()->getTimestamp() === $completedForm->getUpdatedDate()->getTimestamp();

        $row ['_date_created'] = $completedForm->getCreatedDate();
        $row ['_date_modified'] = $createdEqualsModified ? null : $completedForm->getUpdatedDate();

        return $row;
    }

    private function prepareColumns(array $formColumns): array
    {
        $columns = [];
        $colIndex = 0;

        foreach ($formColumns as $column) {
            if (in_array($column['type'], ['header', 'divider', 'row', 'text-entry'])) {
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
}
