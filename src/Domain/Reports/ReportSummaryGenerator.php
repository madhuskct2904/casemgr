<?php

namespace App\Domain\Reports;

use App\Domain\Form\FormSchemaHelper;
use Doctrine\ORM\EntityManagerInterface;

class ReportSummaryGenerator
{
    protected $em;
    protected $reportsEm;
    protected $formHelper;

    public function __construct(
        EntityManagerInterface $entityManager,
        EntityManagerInterface $reportsCacheEntityManager,
        FormSchemaHelper $formHelper
    ) {
        $this->em = $entityManager;
        $this->reportsEm = $reportsCacheEntityManager;
        $this->formHelper = $formHelper;
    }

    public function generateSummary(ReportSummarySettingsDTO $reportSummarySettingsDTO): array
    {
        $summaryColumns = $reportSummarySettingsDTO->getColumns();

        $fieldsFunctionsMap = [];

        $report = $this->em->getRepository('App:Reports')->find($reportSummarySettingsDTO->getReportId());
        $reportTableName = 'report_'.$report->getId().'_'.$reportSummarySettingsDTO->getUserId();

        $summaryBaselineField = $reportSummarySettingsDTO->getBaselineField();
        $baselineField = $summaryBaselineField ?: '_participant_id';
        $columnsLabels = [];
        $colsInReport = [];

        foreach ($summaryColumns as $fieldName => $col) {
            $columnsView[] = [
                'field' => $col['field'],
                'label' => $col['label']
            ];

            $columnsLabels[$col['field']] = $col['label'];

            $fieldsFunctionsMap[$col['field']] = $col['values_function'] ?? 'concat';

            if (!$this->isSystemField($col['field'])) {
                $colsInReport[$fieldName] = "`" . $col['field'] . "`";
            }
        }

        $columnsQueryStr = implode(',', $colsInReport);

        if (!count($colsInReport)) {
            return [
                'name'           => $reportSummarySettingsDTO->getName(),
                'rows'           => [],
                'columns'        => [],
                'columns_labels' => [],
                'initial_sort'   => []
            ];
        }

        $sqlStr = "SELECT _participant_id, $columnsQueryStr FROM $reportTableName ";

        $conditions = $reportSummarySettingsDTO->getConditions();

        if ($conditions && count($conditions)) {
            foreach ($conditions as $fieldConditions) {
                if (isset($fieldConditions['conditions']) && count($fieldConditions['conditions'])) {
                    foreach ($fieldConditions['conditions'] as $fieldCondition) {
                        if ($fieldCondition['type']) {
                            $sqlStr .= "WHERE ";
                            break 2;
                        }
                    }
                }
            }
        }

        $conditionsIndex = 0;

        foreach ($reportSummarySettingsDTO->getConditions() as $conditions) {

            $fieldName = $conditions['field'];

            if (!count($conditions['conditions'])) {
                continue;
            }

            $fieldConditionIndex = 0;

            foreach ($conditions['conditions'] as $condition) {
                if (!isset($condition['type']) || $condition['type'] === '') {
                    continue;
                }

                if ($fieldConditionIndex === 0) {
                    $and = $conditionsIndex === 0 ? '' : 'AND';
                    $sqlStr .= " $and (";
                }

                $conditionsIndex++;

                if ($fieldConditionIndex > 0) {
                    $andOr = (bool)$conditions['all'] ? 'AND' : 'OR';
                } else {
                    $andOr = '';
                }

                $fieldConditionIndex++;

                $type = $condition['type'];
                $value = $condition['value'] ?? '';

                if (isset($condition['date'])) {
                    $now = new \DateTime();
                    switch ($condition['date']) {
                        case 'today':
                            $today = $now->format('m/d/Y');
                            $sqlStr .= $andOr . "`$fieldName` = '$today' ";
                            break;
                        case 'thisweek':
                            $from = $now->modify('Monday this week')->format('Y-m-d');
                            $to = $now->modify('Sunday this week')->format('Y-m-d');
                            $sqlStr .= $andOr . " STR_TO_DATE(`$fieldName`,'%m/%d/%Y') BETWEEN '$from' AND '$to' ";
                            break;
                        case 'thismonth':
                            $from = $now->modify('first day of this month')->format('Y-m-d');
                            $to = $now->modify('last day of this month')->format('Y-m-d');
                            $sqlStr .= $andOr . " STR_TO_DATE(`$fieldName`,'%m/%d/%Y') BETWEEN '$from' AND '$to' ";
                            break;
                        case 'thisyear':
                            $from = $now->modify('first day of January this year')->format('Y-m-d');
                            $to = $now->modify('last day of December this year')->format('Y-m-d');
                            $sqlStr .= $andOr . " STR_TO_DATE(`$fieldName`,'%m/%d/%Y') BETWEEN '$from' AND '$to' ";
                            break;
                        case 'birthmonth':
                            if (isset($condition['value']) && (int)$condition['value'] > 0 && (int)$condition['value'] < 13) {
                                $month = (int)$condition < 10 ? '0' . $condition['value'] : $condition['value'];
                                $sqlStr .= $andOr . " MONTH(STR_TO_DATE(`$fieldName`,'%m/%d/%Y')) = $month";
                            }
                            break;
                        case 'between':
                            $from = $this->convertDateToDefaultFormat($condition['value'][0] ?? null);
                            $to = $this->convertDateToDefaultFormat($condition['value'][1] ?? null);

                            if ($from && $to) {
                                $sqlStr .= $andOr . " STR_TO_DATE(`$fieldName`,'%m/%d/%Y') BETWEEN '$from' AND '$to' ";
                            }
                            break;
                        case 'lessthan':
                            $date = $this->convertDateToDefaultFormat($condition['value'][1]);
                            $sqlStr .= $andOr . " STR_TO_DATE(`$fieldName`,'%m/%d/%Y') < STR_TO_DATE('$date','%m/%d/%Y') ";
                            break;
                        case 'greaterthan':
                            $date = $this->convertDateToDefaultFormat($condition['value'][1]);
                            $sqlStr .= $andOr . " STR_TO_DATE(`$fieldName`,'%m/%d/%Y') > STR_TO_DATE('$date','%m/%d/%Y') ";
                            break;
                        case 'lessorequal':
                            $date = $this->convertDateToDefaultFormat($condition['value'][1]);
                            $sqlStr .= $andOr . " STR_TO_DATE(`$fieldName`,'%m/%d/%Y') <= STR_TO_DATE('$date','%m/%d/%Y') ";
                            break;
                        case 'greaterorequal':
                            $date = $this->convertDateToDefaultFormat($condition['value'][1]);
                            $sqlStr .= $andOr . " STR_TO_DATE(`$fieldName`,'%m/%d/%Y') >= STR_TO_DATE('$date','%m/%d/%Y') ";
                            break;
                        case 'isempty':
                            $sqlStr .= $andOr . "`$fieldName` = '' ";
                            break;
                        case 'isfilled':
                            $sqlStr .= $andOr . "`$fieldName` != '' ";
                            break;
                    }
                    continue;
                }

                if ($type == 'equals') {
                    $sqlStr .= " $andOr `$fieldName` = '$value'";
                }

                if ($type == 'notequal') {
                    $sqlStr .= " $andOr `$fieldName` != '$value'";
                }

                if ($type == 'startswith') {
                    $value = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $value);
                    $sqlStr .= " $andOr `$fieldName` LIKE '$value%'";
                }

                if ($type == 'contains') {
                    $value = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $value);
                    $sqlStr .= " $andOr `$fieldName` LIKE '%$value%'";
                }

                if ($type == 'notcontain') {
                    $value = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $value);
                    $sqlStr .= " $andOr `$fieldName` NOT LIKE '%$value%'";
                }

                if ($type == 'isempty') {
                    $sqlStr .= " $andOr `$fieldName` = '' ";
                }

                if ($type == 'isfilled') {
                    $sqlStr .= " $andOr `$fieldName` != '' ";
                }

                if (in_array($type, ['lessthan', 'greaterthan', 'lessorequal', 'greaterorequal', 'between'])) {
                    if (strpos($fieldName, '_currency') !== false) {
                        $fieldName = "CAST(REPLACE(REPLACE(`$fieldName`, '$', ''),',','') AS DECIMAL(10,4)) ";
                    }

                    if (strpos($fieldName, '_number') !== false) {
                        $fieldName = "CAST(`$fieldName` AS DECIMAL(10,4)) ";
                    }

                    if (strpos($fieldName, '_date') !== false) {
                        $fieldName = STR_TO_DATE(`$fieldName`, '%m/%d/%Y');
                    }

                    $from = $value[0] ?? null;
                    $to = $value[1] ?? null;

                    if ($type == 'lessthan') {
                        $sqlStr .= " $andOr `$fieldName` < $value";
                    }

                    if ($type == 'lessorequal') {
                        $sqlStr .= " $andOr `$fieldName` <= $value";
                    }

                    if ($type == 'greaterorequal') {
                        $sqlStr .= " $andOr `$fieldName` >= $value";
                    }

                    if ($type == 'between') {
                        $sqlStr .= " $andOr `$fieldName` BETWEEN $from AND $to";
                    }
                }
            }

            if ($conditionsIndex > 0 && $fieldConditionIndex > 0) {
                $sqlStr .= " ) ";
            }
        }

        $orderByField = $columnsView[0]['field'];

        if (in_array($orderByField, $colsInReport)) {
            $sqlStr = $sqlStr . ' ORDER BY `' . $orderByField . '`';
        }

        $conn = $this->reportsEm->getConnection();
        $queryResult = $conn->fetchAllAssociative($sqlStr);
        $result = [];

        $getBaselineAllValues = false;
        $countEmptyValues = true;

        foreach ($summaryColumns as $colInSummary) {
            if ($baselineField !== $colInSummary['field']) {
                continue;
            }

            if (!isset($colInSummary['count_null_values']) || !$colInSummary['count_null_values']) {
                $countEmptyValues = false;
            }

            if (!isset($colInSummary['show_all_values']) || $colInSummary['show_all_values']) {
                $getBaselineAllValues = true;
            }
        }

        foreach ($queryResult as $row) {
            $baseLineFieldValue = $row[$baselineField];

            if (!$countEmptyValues && empty($baseLineFieldValue)) {
                continue;
            }

            if (!isset($result[$baseLineFieldValue]['__total'])) {
                $result[$baseLineFieldValue]['__total'] = 1;
            } else {
                $result[$baseLineFieldValue]['__total']++;
            }

            if (!isset($participantResults[$baseLineFieldValue][$row['_participant_id']])) {
                if (!isset($result[$baseLineFieldValue]['__unduplicated'])) {
                    $result[$baseLineFieldValue]['__unduplicated'] = 1;
                } else {
                    $result[$baseLineFieldValue]['__unduplicated']++;
                }

                $participantResults[$baseLineFieldValue][$row['_participant_id']] = true;
            }

            foreach ($row as $fieldName => $fieldValue) {
                if (!$summaryBaselineField && $fieldName === '_participant_id') {
                    continue;
                }

                $result[$baseLineFieldValue][$fieldName][] = $fieldValue;
            }
        }

        if ($getBaselineAllValues) {
            $result = $this->addAllFieldsToResult($baselineField, $result);
        }


        $rows = [];

        foreach ($result as $primaryFieldValue => $values) {
            foreach ($values as $fieldName => $val) {
                if ($this->isSystemField($fieldName)) {
                    continue;
                }

                if (!is_array($val)) {
                    continue;
                }

                switch ($fieldsFunctionsMap[$fieldName]) {
                    case 'sum':
                        $values[$fieldName] = $this->sumValues($val, $fieldName);
                        break;
                    case 'avg':
                        $values[$fieldName] = $this->avgValues($val, $fieldName);
                        break;
                    default:
                        $uniqueValues = array_unique($val);
                        if (count($uniqueValues) === 1) {
                            $values[$fieldName] = $uniqueValues[0];
                        } else {
                            $values[$fieldName] = 'Multiple values: ' . implode(', ', $uniqueValues);
                        }
                }
            }

            if ($summaryBaselineField) {
                $rows[] = [$baselineField => $primaryFieldValue] + $values;
            } else {
                $rows[] = $values;
            }
        }

        $rows = $this->addSummaryRow($result, $rows);

        return [
            'name'           => $reportSummarySettingsDTO->getName(),
            'rows'           => $rows,
            'columns'        => $columnsView,
            'columns_labels' => $columnsLabels,
            'initial_sort'   => $orderByField
        ];
    }

    private function sumValues($values, $fieldName)
    {
        $numericValues = array_map(function ($item) {
            return filter_var($item, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }, $values);

        $sum = round(array_sum($numericValues), 2);

        return $this->formatFieldValue($fieldName, $sum);
    }

    private function avgValues($values, $fieldName)
    {
        $numericValues = array_map(function ($item) {
            return filter_var($item, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }, $values);

        $avg = round(array_sum($numericValues) / count($values), 2);

        return $this->formatFieldValue($fieldName, $avg);
    }

    /**
     * @param $col
     * @return bool
     */
    protected function isSystemField($col): bool
    {
        return strpos($col, '_') === 0;
    }

    /**
     * @param array $result
     * @param array $rows
     * @return array
     */
    private function addSummaryRow(array $result, array $rows): array
    {
        if (!count($result)) {
            return $rows;
        }

        $lastRow = [];
        $ignoreFields = [];

        foreach ($rows as $row) {
            foreach ($row as $columnField => $value) {
                if (in_array($columnField, $ignoreFields)) {
                    continue;
                }

                if (is_array($value)) {
                    $lastRow[$columnField] = '';
                    continue;
                }

                if (preg_match('/[^$0-9\.\, ]/', $value) === 1) {
                    $ignoreFields[] = $columnField;
                    $lastRow[$columnField] = '';
                    continue;
                }

                $value = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

                if (!is_numeric($value)) {
                    $lastRow[$columnField] = '';
                    continue;
                }

                if (isset($lastRow[$columnField]) && is_numeric($lastRow[$columnField])) {
                    $lastRow[$columnField] += $value;
                } else {
                    $lastRow[$columnField] = $value;
                }

                $lastRow[$columnField] = round($lastRow[$columnField], 2);
            }
        }

        foreach ($lastRow as $columnField => $value) {
            $lastRow[$columnField] = $this->formatFieldValue($columnField, $value);
        }

        $rows[] = $lastRow;

        return $rows;
    }

    private function formatFieldValue($fieldName, $value)
    {
        if (strpos($fieldName, '_currency') !== false) {

            if ($value === '') {
                $value = (float)$value;
            }

            return '$ ' . number_format($value, 2, '.', ',');
        }

        return $value;
    }

    /**
     * @param string|null $baselineField
     * @param array $result
     * @return array
     */
    private function addAllFieldsToResult(?string $baselineField, array $result): array
    {
        $formId = substr($baselineField, 0, strpos($baselineField, '_'));
        $form = $this->em->getRepository('App:Forms')->find($formId);

        if (!$form) {
            return $result;
        }

        $fieldOption = substr($baselineField, strrpos($baselineField, '-') + 1);

        if (in_array($fieldOption, ['label', 'value'])) {
            $colName = substr($baselineField, strpos($baselineField, '_') + 1, -(strlen($fieldOption)) - 1);
        } else {
            $colName = substr($baselineField, strpos($baselineField, '_') + 1);
        }

        $formHelper = $this->formHelper;
        $formHelper->setForm($form);
        $formColumn = $formHelper->getColumnByName($colName);

        if (!$formColumn) {
            return $result;
        }

        if (isset($formColumn['values'])) {

            $values = $formColumn['values'];

            foreach ($values as $value) {
                $fieldOption === 'value' ? $labelOrValue = $value['value'] : $labelOrValue = $value['label'];

                if (isset($result[$labelOrValue])) {
                    continue;
                }

                $result[$labelOrValue] = [
                    '__total'        => 0,
                    '__unduplicated' => 0
                ];
            }
        }

        if ($formColumn['type'] === 'checkbox' && !isset($result[$formColumn['label']])) {
            $result[$formColumn['label']] = [
                '__total'        => 0,
                '__unduplicated' => 0
            ];
        }

        return $result;
    }
}
