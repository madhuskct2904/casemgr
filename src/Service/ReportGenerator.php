<?php

namespace App\Service;

use App\Domain\Form\FormSchemaHelper;
use App\Domain\Form\SharedFormPreviewsHelper;
use App\Entity\Forms;
use App\Entity\Reports;
use App\Entity\ReportsForms;
use App\Entity\Users;
use App\Enum\ReferralStatus;
use App\Enum\ReportMode;
use App\Transformers\ReportsTransformer;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use function Sentry\captureEvent;

class ReportGenerator
{
    protected $em;
    protected $reportsEm;
    protected $formHelper;
    protected $dateFormat = 'm/d/Y';

    private $formsMap =
        [
            'multiple' => [],
            'core' => []
        ];

    private $report;
    private $reportForms = [];
    private $preview = false;
    private $previewRowsCount = 10;
    private $managersIds = [];
    private $fieldsNames = [];
    private $columns = [
        ['field' => '_participant_id', 'label' => 'participant_id', 'hidden' => true]
    ];
    private $formsIds = [];
    private $managersNamesMap = [];
    private $managersValuesMap = [];
    private $formFieldsMap = [];
    private $formsData = [];
    private $formIdToDataMap = [];
    private $formDataToIdMap = [];
    private $valuesMap = [];
    private $resultsCount = 0;
    private $matchingFormsDataIds = [];
    private $extraFieldsMap = [];
    private $checkboxesGroupsFields = [];
    private $programsFields = [];
    private $getValueForFields = [];
    private $getLabelForFields = [];
    private $unduplicatedConditionSet = false;
    private $nullForms = [
        'multiple' => [],
        'core' => []
    ]; // forms that can be not filled by participant
    private $casemgrAccountsMap = null;
    private $participantsFilledFormsCountMap = [];
    private $conditionsAppliedToFormsMap = [];

    private $currentChunk = 0;
    private $maxDataIds = 1000; // max data ID's for one chunk for result, 1000 seems the optimum for the code currently, greater numbers seems to decrease performance significantly (5000 is 4 x slower, 7000 is 6x slower)
    private $lastElementId = -1; // last element id can be 0, so we need -1 in first phase
    private $accountsIds;
    private $processedDataIds = [];
    private $referralFormsFields = [];
    private $referralFormsIds = [];

    private $user;
    private $timezones;
    private SharedFormPreviewsHelper $sharedFormPreviewsHelper;

    public function __construct(
        EntityManagerInterface $entityManager,
        EntityManagerInterface $reportsCacheEntityManager,
        FormSchemaHelper $formHelper,
        SharedFormPreviewsHelper $sharedFormPreviewsHelper
    )
    {
        $this->em = $entityManager;
        $this->reportsEm = $reportsCacheEntityManager;
        $this->formHelper = $formHelper;
        $this->sharedFormPreviewsHelper = $sharedFormPreviewsHelper;
    }

    public function setUser(Users $user)
    {
        $this->user = $user;
    }

    public function setDateFormat($dateFormat)
    {
        $this->dateFormat = $dateFormat;
    }

    public function setTimeZones($timezones)
    {
        $this->timezones = $timezones;
    }

    /**
     * Main report generator function.
     *
     * @param Reports $report
     * @param $preview
     * @param $previewRowsCount
     * @return array
     */
    public function generateReport(Reports $report, $preview = false, $previewRowsCount = 10): array
    {
        // ALL METHODS NEEDS TO BE RUN IN ORDER
        $this->preview = $preview;
        $this->previewRowsCount = $previewRowsCount;
        $this->report = $report;
        $this->accountsIds = $this->getAccountsIds($this->report);

        $excludeDataIds = $this->getClonedProfiles();

        if ($this->preview) {
            $this->maxDataIds = $previewRowsCount * 10;
        }

        $allResults = [];

        $startTime = time();

        do {
            $this->resetGeneratorFirst();

            $reportHasData = $this->mapReportForms($excludeDataIds);

            if (!$reportHasData) {
                if (!$this->preview) {
                    $this->UpdateReport();
                }
                break;
            }

            $this->resetGeneratorSecond();
            $this->mapFormsData();

            $this->setManagersFields();

            $this->managersNamesMap = $this->mapManagersIdsToNames();

            $this->parseReportFields();
            $this->getValuesMap();
            $this->getExtraFieldsValues();

            $results = $this->generateResults();
            $formattedResults = $this->formatResult($results);

            foreach ($formattedResults as $formattedResult) {
                $allResults[] = $formattedResult;
            }

            if (!$this->preview) {
                $this->saveReportToCache($formattedResults);
            }

            $this->currentChunk++;

            if ($this->preview &&
             ((count($allResults) >= $previewRowsCount) || (time() - $startTime > 30))) {
                break;
            }
        } while (true);

        $allResults = array_splice($allResults, 0, $this->previewRowsCount);

        foreach ($allResults as &$result) {
            unset($result['_participant_id']);
        }


        $cols = [];
        foreach ($this->columns as $column) {
            if ($column['field'] === '_participant_id') {
                continue;
            }
            if (array_key_exists('forms_data', $column) === false) {
                $cols[] = $column;
            }
        }

        return [
            'count'     => $this->resultsCount,
            'results'   => $allResults,
            'columns'   => $cols,
            'isPreview' => (bool) $preview,
        ];
    }

    /**
     * @param Reports $report
     * @return array
     */
    private function getAccountsIds(Reports $report): array
    {
        $mode = $report->getMode() ? $report->getMode() : ReportMode::SINGLE;

        if ($mode == ReportMode::MULTIPLE || $mode == ReportMode::MULTIPLE_MIRROR_PARENT) {
            $accounts = $report->getAccounts();
            return array_column(json_decode($accounts, true), 'id');
        }

        return [$report->getAccount()->getId()];
    }

    /**
     * @return mixed
     */
    private function getClonedProfiles()
    {
        return $this->em->getRepository('App:FormsData')->getClonedProfiles($this->report->getAccount()->getId());
    }

    private function resetGeneratorFirst() {
        $this->formsIds = [];
        $this->formFieldsMap = [];
        $this->formsMap =
        [
            'multiple' => [],
            'core' => []
        ];
        $this->nullForms = [
            'multiple' => [],
            'core' => []
        ]; // forms that can be not filled by participant
        $this->reportForms = [];
        $this->formsData = [];
        $this->managersIds = [];
        $this->fieldsNames = [];
        $this->managersNamesMap = [];
        $this->managersValuesMap = [];

        $this->formIdToDataMap = [];
        $this->formDataToIdMap = [];
        $this->valuesMap = [];
        $this->matchingFormsDataIds = [];
        $this->extraFieldsMap = [];
        $this->checkboxesGroupsFields = [];
        $this->getValueForFields = [];
        $this->getLabelForFields = [];
        $this->unduplicatedConditionSet = false;
        $this->casemgrAccountsMap = null;
        $this->participantsFilledFormsCountMap = [];
    }


    private function resetGeneratorSecond()
    {
        $this->columns = [
            ['field' => '_participant_id', 'label' => 'participant_id', 'hidden' => true]
        ];
    }

    /**
     * Map Report forms to internal maps, returns false if form does not contains valid forms
     *
     * @param $excludeDataIds
     * @return bool
     */
    private function mapReportForms($excludeDataIds): bool
    {
        $reportData = json_decode($this->report->getData(), true);
        $nullForms = [];

        foreach ($reportData as $formInReport) {
            $this->formsIds[] = $formInReport['form_id'];
            $this->formFieldsMap[$formInReport['form_id']] = $formInReport['fields'];

            if (isset($formInReport['isNull']) && $formInReport['isNull']) {
                $nullForms[] = $formInReport['form_id'];
            }
        }

        $forms = $this->em->getRepository('App:Forms')->findById($this->formsIds);

        if (!$forms) {
            return false;
        }

        foreach ($forms as $form) {
            $formId = $form->getId();
            $isCore = $form->getModule()->getGroup() == 'core';

            if ($form->getModule()->getRole() == 'referral') {
                $this->referralFormsIds[] = $formId;
            }

            $this->formsMap[$isCore ? 'core' : 'multiple'][] = $formId;
            $this->reportForms[$form->getId()] = $form;

            if (in_array($formId, $nullForms)) {
                $this->nullForms[$isCore ? 'core' : 'multiple'][] = $formId;
            }
        }

        $formsData = $this->getFormsData($excludeDataIds, $this->formsIds, $this->accountsIds);

        if (!$formsData) {
            return false;
        }

        // unset all removed forms from report
        if (count($removedForms = array_diff(array_keys($this->formFieldsMap), array_keys($this->reportForms)))) {
            foreach ($removedForms as $removedFormId) {
                unset($this->formFieldsMap[$removedFormId]);
            }
        }

        if (!count($this->formFieldsMap)) {
            return false;
        }

        $this->formsData = $formsData;

        return true;
    }

    /**
     * @param $excludeDataIds
     * @param array $formsIds
     * @param array $accountsIds
     * @return mixed
     */
    private function getFormsData($excludeDataIds, array $formsIds, array $accountsIds): array
    {
        $formsIdsStr = implode(',', $formsIds);

        $accountsIdsStr = '';

        if (count($accountsIds)) {
            $accountsIdsStr = 'AND `account_id` IN(' . implode(',', $accountsIds) . ')';
//            $accountsIdsStr = 'AND fd.account_id IN(' . implode(',', $accountsIds) . ')';
        }

        $excludeDataIdsStr = '';

        if (count($excludeDataIds)) {
            $excludeDataIdsStr = 'AND `id` NOT IN (' . implode(',', $excludeDataIds) . ')';
//            $excludeDataIdsStr = 'AND fd.id NOT IN (' . implode(',', $excludeDataIds) . ')';
        }

        $limit = $this->maxDataIds;
        $sql   = "SELECT `id`,`module_id`,`form_id`, `element_id`, `creator_id`, `editor_id`, `created_date` as `created_date_raw`, DATE_FORMAT(`created_date`,'|%m/%d/%Y|%h:%i %p') as `created_date`, `updated_date` as `updated_date_raw`, DATE_FORMAT(`updated_date`, '|%m/%d/%Y|%h:%i %p') as `updated_date`, `manager_id`, `secondary_manager_id`, `assignment_id`, `account_id`
FROM forms_data WHERE `form_id` IN ($formsIdsStr) $accountsIdsStr $excludeDataIdsStr AND `element_id` > $this->lastElementId ORDER BY element_id LIMIT $limit";

        $conn = $this->em->getConnection();
        $result = $conn->fetchAllAssociative($sql);

        $ids     = array_column($result, 'id');
        $lastRow = end($result);

        if (false === empty($lastRow)) {
            $this->lastElementId = $lastRow['element_id'];
        }

        if (false === empty($ids)) {
            $idsStr = implode(',', $ids);
            $sql    = "SELECT `id`,`module_id`,`form_id`, `element_id`, `creator_id`, `editor_id`, `created_date` as `created_date_raw`, DATE_FORMAT(`created_date`,'|%m/%d/%Y|%h:%i %p') as `created_date`, `updated_date` as `updated_date_raw`, DATE_FORMAT(`updated_date`, '|%m/%d/%Y|%h:%i %p') as `updated_date`, `manager_id`, `secondary_manager_id`, `assignment_id`, `account_id`
FROM forms_data WHERE `form_id` IN ($formsIdsStr) $accountsIdsStr $excludeDataIdsStr AND `element_id` = $this->lastElementId AND `id` NOT IN ($idsStr)";

            $result = array_merge(
                $result,
                $conn->fetchAllAssociative($sql)
            );
        }

        return $result;
    }

    private function mapFormsData(): void
    {
        foreach ($this->formsData as $formData) {
            $this->formIdToDataMap[$formData['form_id']][] = $formData['id'];
            $this->formDataToIdMap[$formData['id']] = $formData['form_id'];
            $this->valuesMap[$formData['id']] = [];

            isset($this->participantsFilledFormsCountMap[$formData['element_id']][$formData['form_id']])
                ? ++$this->participantsFilledFormsCountMap[$formData['element_id']][$formData['form_id']]
                : $this->participantsFilledFormsCountMap[$formData['element_id']][$formData['form_id']] = 1;
        }
    }

    /**
     * Set managers IDs from report data for later usage (get manager names from db along with manager names from forms values in one shot)
     */
    private function setManagersFields(): ?array
    {
        $conn = $this->em->getConnection();
        $managersFields = [];

        foreach ($this->formFieldsMap as $formId => $fieldsArr) {
            foreach ($fieldsArr as $field) {
                if (strpos($field['field'], 'select2-') === 0 || strpos($field['field'], 'select_case_manager_secondary-') === 0) {
                    $managersFields[] = $field['field'];
                }
            }
        }

        $results = [];

        $formsDataIds = implode(',', array_column($this->formsData, 'id'));

        $foundManagerIds = [];
        if (count($managersFields)) {
            foreach ($managersFields as $managerField) {
                $sql = "SELECT `value`, `data_id`, `name` FROM forms_values WHERE `name` ='$managerField' AND `data_id` IN($formsDataIds)";
                $results = $conn->fetchAllAssociative($sql);
                $foundDataIds = [];

                foreach ($results as $res) {
                    $this->managersValuesMap[$managerField][$res['value'] ?: 0][] = $res['data_id'];
                    $foundDataIds[] = $res['data_id'];
                    $foundManagerIds[] = $res['value'] ?: 0;
                }

                $missingIds = array_diff(array_column($this->formsData, 'id'), array_unique($foundDataIds));

                if (count($missingIds)) {
                    $this->managersValuesMap[$managerField][0] = isset($this->managersValuesMap[$managerField][0]) ? array_merge($this->managersValuesMap[$managerField][0], $missingIds) : $missingIds;
                }
            }
        }

        $sql = "SELECT id, creator_id, editor_id FROM forms_data WHERE id in ($formsDataIds)";
        $result = $conn->fetchAllAssociative($sql);

        foreach ($result as $res) {
            $this->managersValuesMap['_creator_id'][$res['creator_id'] ?: 0][] = $res['id'];
            $this->managersValuesMap['_editor_id'][$res['editor_id'] ?: 0][] = $res['id'];
        }

        $referralManagersResult = [];

        if (count($this->referralFormsIds)) {
            $referralManagersSql = "SELECT last_action_user, data_id FROM referral WHERE data_id IN($formsDataIds)";
            $referralManagersResult = $conn->fetchAllAssociative($referralManagersSql);

            foreach ($referralManagersResult as $res) {
                $this->managersValuesMap['_referral_last_action_user'][$res['last_action_user'] ?: 0][] = $res['data_id'];
            }

        }

        return $this->managersIds = array_values(array_unique(array_merge(
            array_column($this->formsData, 'manager_id'),
            array_column($this->formsData, 'creator_id'),
            array_column($this->formsData, 'editor_id'),
            array_column($referralManagersResult, 'last_action_user'),
            array_column($results, 'value'),
            array_values(array_unique($foundManagerIds))
        )));
    }

    /**
     * Map managers IDs to real names
     *
     * @return array
     */
    private function mapManagersIdsToNames(): array
    {
        $managersMap = [];
        if (count($this->managersIds)) {
            $managers = $this->em->getRepository('App:UsersData')->findByUser($this->managersIds);

            foreach ($managers as $manager) {
                $managersMap[$manager->getUser()->getId()] = $manager->getFullName();
            }
        }

        return $managersMap;
    }

    /**
     * Parse report fields, set all necessary internal data, add reports columns
     *
     * @return void
     */
    private function parseReportFields(): void
    {
        foreach ($this->formFieldsMap as $formId => $fields) {
            $formDataIds = $this->formIdToDataMap[$formId] ?? [];
            $this->matchingFormsDataIds[$formId] = $formDataIds;

            foreach ($fields as $field) {
                $fieldName = $field['field'];
                $fieldLabel = $field['label'];

                $fieldConditions = $this->fixConditionsOrder($field['conditions']);


                if (isset($field['conditions'])) {
                    foreach ($field['conditions'] as $fieldCondition) {
                        if (isset($fieldCondition['type']) && $fieldCondition['type'] == 'unduplicated') {
                            $this->unduplicatedConditionSet = true;
                        }
                    }

                    $any = isset($field['allConditions']) && ($field['allConditions'] === false);


                    if (count($this->matchingFormsDataIds[$formId])) {
                        $formsIdsWithConditionsMet = null;

                        // standard fields (special fields starts with "_"), ignore select2 (primary manager select)
                        if (strpos($fieldName, '_') !== 0 && strpos($fieldName, 'select2') !== 0) {
                            $formsIdsWithConditionsMet = $this->applyConditions($fieldName, $formId, $this->matchingFormsDataIds[$formId], $fieldConditions, $any);
                        }

                        if (strpos($fieldName, '_creator_id') === 0) {
                            $formsIdsWithConditionsMet = $this->applyConditionsForManagerField($fieldName, $this->matchingFormsDataIds[$formId], $fieldConditions, $any);
                        }

                        if (strpos($fieldName, '_editor_id') === 0) {
                            $formsIdsWithConditionsMet = $this->applyConditionsForManagerField($fieldName, $this->matchingFormsDataIds[$formId], $fieldConditions, $any);
                        }

                        if ($fieldName === '_casemgr_account') {
                            $formsIdsWithConditionsMet = $this->applyConditionsForCaseMgrAccount($this->matchingFormsDataIds[$formId], $fieldConditions, $any);
                        }

                        if (strpos($fieldName, 'select2') === 0) {
                            $formsIdsWithConditionsMet = $this->applyConditionsForManagerField($fieldName, $this->matchingFormsDataIds[$formId], $fieldConditions, $any);
                        }

                        // set only good data ids if condition applied (!== null)
                        if ($formsIdsWithConditionsMet !== null) {
                            $this->matchingFormsDataIds[$formId] = $formsIdsWithConditionsMet;
                        }
                    }
                }

                if (isset($field['columns'])) {
                    if (in_array('label', $field['columns'])) {
                        $this->columns[] = [
                            'field' => $formId . '_' . $field['field'] . '-label',
                            'label' => count($field['columns']) > 1 ? $fieldLabel . ' Label' : $fieldLabel
                        ];

                        $this->getLabelForFields[] = $fieldName;
                    }

                    if (in_array('value', $field['columns'])) {
                        $this->columns[] = [
                            'field' => $formId . '_' . $field['field'] . '-value',
                            'label' => count($field['columns']) > 1 ? $fieldLabel . ' Value' : $fieldLabel
                        ];

                        $this->getValueForFields[] = $fieldName;
                    }

                    if (!in_array($formId, $this->extraFieldsMap) || !in_array($fieldName, $this->extraFieldsMap[$formId])) {
                        $this->extraFieldsMap[$formId][] = $fieldName;
                    }

                }

                if (!isset($field['columns'])) {
                    $this->columns[] = [
                        'field' => $formId . '_' . $fieldName,
                        'label' => $fieldLabel
                    ];
                }

                $this->fieldsNames[] = $fieldName;

                // checkbox-groups need special treatment, so we must find them in another way
                if (strpos($fieldName, 'checkbox-group') === 0) {
                    if (!in_array($fieldName, $this->checkboxesGroupsFields)) {
                        $this->checkboxesGroupsFields[] = $fieldName;
                    }
                    continue;
                }

                if (strpos($fieldName, 'programs-checkbox-group') === 0) {
                    if (!in_array($fieldName, $this->programsFields)) {
                        $this->programsFields[] = $fieldName;
                    }
                    continue;
                }

                if (strpos($fieldName, '_referral') === 0) {
                    if (!in_array($fieldName, $this->referralFormsFields)) {
                        $this->referralFormsFields[] = $fieldName;
                    }
                    continue;
                }

                if (($fieldName === '_casemgr_account' || $fieldName === '_organization_name') && $this->casemgrAccountsMap === null) {
                    $this->setCaseMgrAccountsMap();
                }
            }
        }
    }

    /**
     * Fixes conditions order - "filtering" conditions should be added as last. For example "recent" is filtering condition.
     *
     * @param $conditions
     * @return array
     */
    private function fixConditionsOrder($conditions): array
    {
        if (!is_array($conditions) || !count($conditions)) {
            return [];
        }

        $lastConditions = [];

        foreach ($conditions as $key => $condition) {
            if (isset($condition['type']) && ($condition['type'] == 'recent')) {
                $lastConditions[] = $condition;
                unset($conditions[$key]);
            }
        }

        return array_merge($conditions, $lastConditions);
    }

    private function applyConditions(string $fieldName, int $formId, array $dataIds, array $conditions, bool $any): ?array
    {
        $tableName = 'forms_values';
        $valueField = '`value`';
        $convertDateField = true;

        if (strpos($fieldName, '_referral') === 0) {
            $tableName = 'referral';

            $valueFieldsReferrals = [
                '_referral_status' => 'status',
                '_referral_not_enrolled_reason' => 'comment',
                '_referral_timestamp_received' => 'created_at',
                '_referral_timestamp_completed' => 'created_at',
                '_referral_timestamp_not_enrolled' => 'last_action_at',
            ];

            $convertDateField = false;
            $valueField = $valueFieldsReferrals[$fieldName];
        }

        $dataIdsStr = implode(',', $dataIds);
        $conn = $this->em->getConnection();

        $sqlStr = '';
        $setParameters = [];

        $andOr = $any ? 'OR' : 'AND';

        $numericValues = [];

        $results = [];

        $findRecent = [];

        $isEmptyConditionSet = false;

        foreach ($conditions as $idx => $condition) {
            if (isset($condition['type'])) {
                $type = $condition['type'];
                $value = $condition['value'] ?? '';

                if ($type == 'equals') {
                    $sqlStr .= $andOr . $valueField . ' = ? ';
                    $setParameters[] = "$value";
                }

                if ($type == 'notequal') {
                    $sqlStr .= $andOr . $valueField . ' != ? ';
                    $setParameters[] = "$value";
                }

                if ($type == 'startswith') {
                    $value = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $value);
                    $sqlStr .= $andOr . $valueField . ' LIKE ? ';
                    $setParameters[] = "$value%";
                }

                if ($type == 'contains') {
                    $value = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $value);
                    $sqlStr .= $andOr . $valueField . ' LIKE ? ';
                    $setParameters[] = "%$value%";
                }

                if ($type == 'notcontain') {
                    $value = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $value);
                    $sqlStr .= $andOr . $valueField . ' NOT LIKE ? ';
                    $setParameters[] = "%$value%";
                }

                if (in_array($type, ['lessthan', 'greaterthan', 'lessorequal', 'greaterorequal', 'between'])) {
                    $numericValues[] = [
                        'type' => $type,
                        'value' => $value,
                        'from' => $value[0] ?? null,
                        'to' => $value[1] ?? null,
                    ];
                }

                if ($type == 'recent') {
                    $findRecent[] = $value;
                }

                if ($type == 'isempty') {
                    $sqlStr .= $andOr . "$valueField = '' ";
                    $isEmptyConditionSet = true;
                }

                if ($type == 'isfilled') {
                    $sqlStr .= $andOr . "$valueField != '' ";
                }
            }

            if (isset($condition['date'])) {
                $now = new DateTime();
                $dateValue = $convertDateField ? "STR_TO_DATE($valueField,'%m/%d/%Y')" : $valueField;

                switch ($condition['date']) {
                    case 'today':
                        $today = $now->format('m/d/Y');
                        $sqlStr .= $andOr . $valueField . ' = ? ';
                        $setParameters[] = $today;
                        break;
                    case 'thisweek':
                        $from = $now->modify('Monday this week')->format('Y-m-d');
                        $to = $now->modify('Sunday this week')->format('Y-m-d');
                        $sqlStr .= $andOr . " $dateValue BETWEEN '$from' AND '$to' ";
                        break;
                    case 'thismonth':
                        $from = $now->modify('first day of this month')->format('Y-m-d');
                        $to = $now->modify('last day of this month')->format('Y-m-d');
                        $sqlStr .= $andOr . " $dateValue BETWEEN '$from' AND '$to' ";
                        break;
                    case 'thisyear':
                        $from = $now->modify('first day of January this year')->format('Y-m-d');
                        $to = $now->modify('last day of December this year')->format('Y-m-d');
                        $sqlStr .= $andOr . " $dateValue BETWEEN '$from' AND '$to' ";
                        break;
                    case 'birthmonth':
                        if (isset($condition['value']) && (int)$condition['value'] > 0 && (int)$condition['value'] < 13) {
                            $month = (int)$condition < 10 ? '0' . $condition['value'] : $condition['value'];
                            $sqlStr .= $andOr . " MONTH($dateValue) = $month";
                        }
                        break;
                    case 'between':
                        $from = $this->convertDateToDefaultFormat($condition['value'][0] ?? null);
                        $to = $this->convertDateToDefaultFormat($condition['value'][1] ?? null);

                        if ($from && $to) {
                            $sqlStr .= $andOr . " $dateValue BETWEEN STR_TO_DATE('$from', '%m/%d/%Y') AND STR_TO_DATE('$to', '%m/%d/%Y') ";
                        }
                        break;
                    case 'lessthan':
                        $date = $this->convertDateToDefaultFormat($condition['value']);
                        $sqlStr .= $andOr . " $dateValue < STR_TO_DATE('$date','%m/%d/%Y') ";
                        break;
                    case 'greaterthan':
                        $date = $this->convertDateToDefaultFormat($condition['value']);
                        $sqlStr .= $andOr . " $dateValue > STR_TO_DATE('$date','%m/%d/%Y') ";
                        break;
                    case 'lessorequal':
                        $date = $this->convertDateToDefaultFormat($condition['value']);
                        $sqlStr .= $andOr . " $dateValue <= STR_TO_DATE('$date','%m/%d/%Y') ";
                        break;
                    case 'greaterorequal':
                        $date = $this->convertDateToDefaultFormat($condition['value']);
                        $sqlStr .= $andOr . " $dateValue >= STR_TO_DATE('$date','%m/%d/%Y') ";
                        break;
                    case 'isempty':
                        $sqlStr .= $andOr . "$valueField = '' ";
                        break;
                    case 'isfilled':
                        $sqlStr .= $andOr . "$valueField != '' ";
                        break;
                    case 'recent':
                        $findRecent[] = $fieldName;
                        break;
                }
            }
        }

        if (!$sqlStr && !count($numericValues) && !count($findRecent)) {
            // no conditions are applied
            return null;
        }

        if (count($numericValues)) {
            if (strpos($fieldName, 'checkbox-group') === 0) {
                $sql = "SELECT `data_id`, `value` FROM $tableName WHERE `name` LIKE '$fieldName%' AND `data_id` IN($dataIdsStr)";
            } else {
                $sql = "SELECT `data_id`, `value` FROM $tableName WHERE `name` = '$fieldName' AND `data_id` IN($dataIdsStr)";
            }

            $res2 = $conn->fetchAllAssociative($sql);

            foreach ($res2 as $res) {
                foreach ($numericValues as $numericValue) {
                    $val2 = filter_var($res['value'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                    $type = $numericValue['type'];

                    switch ($type) {
                        case 'lessthan':
                            if ($val2 < (float)$numericValue['value']) {
                                $results[] = $res['data_id'];
                            }
                            break;
                        case 'greaterthan':
                            if ($val2 > (float)$numericValue['value']) {
                                $results[] = $res['data_id'];
                            }
                            break;
                        case 'lessorequal':
                            if ($val2 <= (float)$numericValue['value']) {
                                $results[] = $res['data_id'];
                            }
                            break;
                        case 'greaterorequal':
                            if ($val2 >= (float)$numericValue['value']) {
                                $results[] = $res['data_id'];
                            }
                            break;
                        case 'between':
                            if ($val2 >= (float)$numericValue['from'] && $val2 <= (float)$numericValue['to']) {
                                $results[] = $res['data_id'];
                            }
                            break;
                    }
                }
            }
        }

        if ($sqlStr) {
            $sqlStr = ltrim($sqlStr, $andOr);

            if (strpos($fieldName, 'checkbox-group') === 0) {
                $sql = "SELECT `data_id` FROM $tableName WHERE `name` LIKE '$fieldName%' AND `data_id` IN($dataIdsStr) AND (" . $sqlStr . ')';
            } elseif (strpos($fieldName, 'programs-checkbox-group') === 0) {
                $sqlStr = str_replace('`value`', 'p.name', $sqlStr);
                $sql = "SELECT `data_id` FROM $tableName fv JOIN programs p ON fv.value = p.id WHERE fv.name LIKE '$fieldName%' AND `data_id` IN($dataIdsStr) AND (" . $sqlStr . ')';
            } elseif (strpos($fieldName, '_referral') === 0) {
                $sql = "SELECT `data_id` FROM $tableName WHERE  `data_id` IN($dataIdsStr) AND (" . $sqlStr . ')';
            } else {
                $sql = "SELECT `data_id` FROM $tableName WHERE `name` = '$fieldName' AND `data_id` IN($dataIdsStr) AND (" . $sqlStr . ')';
            }

            $rsm = new ResultSetMapping();
            $rsm->addScalarResult('data_id', 'data_id');

            $query = $this->em->createNativeQuery($sql, $rsm);

            foreach ($setParameters as $idx => $setParameter) {
                $query->setParameter($idx + 1, $setParameter);
            }

            $conditionResult = array_column($query->getResult(), 'data_id');

            // additional check for empty results - get all data ids with values, and take ones without corresponding form_value
            if ($isEmptyConditionSet && $tableName == 'forms_values') {
                $sql = "SELECT `data_id` FROM $tableName WHERE `name` = '$fieldName' AND `data_id` IN($dataIdsStr)";
                $rsm = new ResultSetMapping();
                $rsm->addScalarResult('data_id', 'data_id');
                $query = $this->em->createNativeQuery($sql, $rsm);
                $allDataIdsWithValues = array_column($query->getResult(), 'data_id');
                $conditionResult += array_diff($dataIds, $allDataIdsWithValues);
            }

            $results += $conditionResult;
        }

        // filtering conditions

        if (count($findRecent) && !($sqlStr && !count($results))) {
            $results = count($results) ? implode(',', $results) : $dataIdsStr;

            foreach ($findRecent as $idx => $findRecentField) {
                if ($findRecentField == 'createdDate') {
                    $sql = "SELECT fd.id AS `data_id`, UNIX_TIMESTAMP(fd.created_date) AS `value`, fd.element_id AS `participant_id` FROM forms_data fd WHERE fd.id IN($results)";
                }

                if ($findRecentField == 'updatedDate') {
                    $sql = "SELECT fd.id AS `data_id`, UNIX_TIMESTAMP(fd.updated_date) AS `value`, fd.element_id AS `participant_id` FROM forms_data fd WHERE fd.id IN($results)";
                }

                if ($findRecentField != 'updatedDate' && $findRecentField != 'createdDate') {
                    $sql = "SELECT fv.data_id AS `data_id`, UNIX_TIMESTAMP(STR_TO_DATE(fv.value,'%m/%d/%Y')) AS `value`, fd.element_id AS `participant_id`
                        FROM $tableName fv
                        JOIN forms_data fd
                        ON fv.data_id = fd.id
                        WHERE fv.name = '$findRecentField' AND fv.data_id IN($results)";
                }

                $rsm = new ResultSetMapping();
                $rsm->addScalarResult('data_id', 'data_id');
                $rsm->addScalarResult('value', 'value');
                $rsm->addScalarResult('participant_id', 'participant_id');

                $query = $this->em->createNativeQuery($sql, $rsm);

                $res2 = $query->getResult();

                $recentDateForParticipant = [];
                $mostRecentDataIds = [];

                foreach ($res2 as $item2) {
                    $datetime = $item2['value'];
                    if (!$datetime) {
                        continue;
                    }

                    $timestamp = $datetime;

                    if (!isset($recentDateForParticipant[$item2['participant_id']])) {
                        $recentDateForParticipant[$item2['participant_id']] = $timestamp;
                        $mostRecentDataIds[$item2['participant_id']] = $item2['data_id'];
                        continue;
                    }

                    if ($timestamp > $recentDateForParticipant[$item2['participant_id']]) {
                        $recentDateForParticipant[$item2['participant_id']] = $timestamp;
                        $mostRecentDataIds[$item2['participant_id']] = $item2['data_id'];
                        continue;
                    }
                }

                $results = $mostRecentDataIds;
            }
        }

        $this->conditionsAppliedToFormsMap[$formId] = true;

        return $results;
    }

    private function convertDateToDefaultFormat($date)
    {
        if (!$date) {
            return null;
        }

        if ($this->dateFormat == 'm/d/Y') {
            return $date;
        }

        $date = DateTime::createFromFormat($this->dateFormat, $date);
        return $date->format('m/d/Y');
    }

    private function applyConditionsForManagerField(string $fieldName, array $dataIds, array $conditions, bool $any)
    {
        $uniqueManagersIds = array_keys($this->managersValuesMap[$fieldName]);

        $searchIn = [];

        foreach ($uniqueManagersIds as $managerId) {
            if (empty($managerId)) {
                $searchIn[0] = '';
                continue;
            }
            $searchIn[$managerId] = $this->managersNamesMap[$managerId];
        }

        $meetConditionManagersIds = [];

        foreach ($searchIn as $managerId => $managerName) {
            $managerName = strtoupper($managerName);

            $meetConditionManagersIds[$managerId] = true;

            foreach ($conditions as $idx => $condition) {
                if (isset($condition['type'])) {
                    $type = $condition['type'];
                    $value = strtoupper($condition['value'] ?? '');
                    $meetConditionManagersIds[$managerId] = false;

                    if ($type == 'equals' && $managerName == $value) {
                        $meetConditionManagersIds[$managerId] = true;
                    }

                    if ($type == 'notequal' && $managerName != $value) {
                        $meetConditionManagersIds[$managerId] = true;
                    }

                    if ($value && $type == 'startswith' && strpos($managerName, $value) === 0) {
                        $meetConditionManagersIds[$managerId] = true;
                    }

                    if ($value && $type == 'contains' && strpos($managerName, $value) !== false) {
                        $meetConditionManagersIds[$managerId] = true;
                    }

                    if ($value && $type == 'notcontain' && strpos($managerName, $value) === false) {
                        $meetConditionManagersIds[$managerId] = true;
                    }

                    if ($type == 'isempty' && empty($managerName)) {
                        $meetConditionManagersIds[$managerId] = true;
                    }

                    if ($type == 'isfilled' && strlen($managerName)) {
                        $meetConditionManagersIds[$managerId] = true;
                    }

                    if ($meetConditionManagersIds[$managerId] && $any) {
                        break;
                    }
                }
            }
        }

        $results = [];

        foreach ($meetConditionManagersIds as $managerId => $state) {
            if ($state) {
                $results = array_merge($results, $this->managersValuesMap[$fieldName][$managerId]);
            }
        }

        $results = array_intersect($results, $dataIds);

        return $results;
    }

    private function applyConditionsForCaseMgrAccount(array $dataIds, array $conditions, bool $any)
    {
        $tableName = 'accounts';
        $dataIds = implode(',', $dataIds);

        $sqlStr = '';
        $setParameters = [];

        $andOr = $any ? 'OR' : 'AND';

        $accountsIds = [];

        foreach ($conditions as $idx => $condition) {
            if (isset($condition['type'])) {
                $type = $condition['type'];
                $value = $condition['forField']['field'] ?? '';

                if ($type == 'equals') {
                    $sqlStr .= $andOr . '`organization_name` = ? ';
                    $setParameters[] = "$value";
                }

                if ($type == 'notequal') {
                    $sqlStr .= $andOr . '`organization_name` != ? ';
                    $setParameters[] = "$value";
                }

                if ($type == 'startswith') {
                    $value = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $value);
                    $sqlStr .= $andOr . '`organization_name` LIKE ? ';
                    $setParameters[] = "$value%";
                }

                if ($type == 'contains') {
                    $value = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $value);
                    $sqlStr .= $andOr . '`organization_name` LIKE ? ';
                    $setParameters[] = "%$value%";
                }

                if ($type == 'notcontain') {
                    $value = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $value);
                    $sqlStr .= $andOr . '`organization_name` NOT LIKE ? ';
                    $setParameters[] = "%$value%";
                }
            }
        }

        if (!$sqlStr) {
            // no conditions are applied
            return null;
        }

        $sqlStr = ltrim($sqlStr, $andOr);

        $sql = "SELECT `id` FROM $tableName WHERE $sqlStr";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id');

        $query = $this->em->createNativeQuery($sql, $rsm);

        foreach ($setParameters as $idx => $setParameter) {
            $query->setParameter($idx + 1, $setParameter);
        }

        $accountsIds += array_column($query->getResult(), 'id');

        if (!count($accountsIds)) {
            return [];
        }

        $accountsIdsStr = implode(',', $accountsIds);

        $sql2 = "SELECT `id` FROM `forms_data` WHERE `account_id` IN ($accountsIdsStr)  AND `id` IN($dataIds)";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id', 'id');

        $query = $this->em->createNativeQuery($sql2, $rsm);

        return array_column($query->getResult(), 'id');
    }

    private function setCaseMgrAccountsMap()
    {
        $tableName = 'accounts';
        $accountsIds = array_unique(array_column($this->formsData, 'account_id'));

        if (!count($accountsIds)) {
            $this->casemgrAccountsMap = null;
            return null;
        }

        $accountsIdsStr = implode(',', $accountsIds);

        $sql = "SELECT `organization_name`,`id` FROM $tableName WHERE `id` IN ($accountsIdsStr)";

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('organization_name', 'organization_name');
        $rsm->addScalarResult('id', 'id');


        $result = $this->em->createNativeQuery($sql, $rsm)->getResult();

        foreach ($result as $item) {
            $this->casemgrAccountsMap[$item['id']] = $item['organization_name'];
        }
    }

    private function getValuesMap(): array
    {
        $dataIds = [];
        $conn = $this->em->getConnection();

        foreach ($this->matchingFormsDataIds as $formId => $goodDataIds) {
            foreach ($goodDataIds as $dataId) {
                $dataIds[] = $dataId;
            }
        }

        $this->processedDataIds = array_merge($this->processedDataIds, $dataIds);

        if (!count($dataIds)) {
            return $this->valuesMap;
        }

        $dataIds = implode(',', $dataIds);

        $names = '';

        foreach ($this->fieldsNames as $fieldName) {
            $names .= "'$fieldName',";
        }

        $names = rtrim($names, ',');

        $sql = "SELECT `name`,`value`,`data_id` FROM forms_values WHERE `name` IN ($names) AND `data_id` IN($dataIds) ";
        $results = $conn->fetchAllAssociative($sql);

        foreach ($results as $result) {
            $this->valuesMap[$result['data_id']][$result['name']] = $result['value'];

            if (strpos($result['name'], 'signature-') !== false) {
                $this->valuesMap[$result['data_id']][$result['name']] = empty($result['value']) ? '' : 'Signed';
            }
        }

        if (count($this->checkboxesGroupsFields)) {
            foreach ($this->checkboxesGroupsFields as $checkboxesGroup) {
                $sql2 = "SELECT `value`,`data_id` FROM forms_values WHERE `name` LIKE '$checkboxesGroup%' AND `data_id` IN ($dataIds)";
                $results = $conn->fetchAllAssociative($sql2);

                foreach ($results as $result) {
                    $val = $result['value'];
                    isset($this->valuesMap[$result['data_id']][$checkboxesGroup])
                        ? $this->valuesMap[$result['data_id']][$checkboxesGroup] .= ', ' . $val
                        : $this->valuesMap[$result['data_id']][$checkboxesGroup] = $val;
                }
            }
        }

        if (count($this->programsFields)) {

            foreach ($this->programsFields as $programsFieldName) {

                $programsSql = "SELECT p.name as `value`,`data_id` FROM forms_values fv JOIN programs p ON fv.value = p.id WHERE fv.data_id IN ($dataIds) AND fv.name LIKE '$programsFieldName%' ";
                $results = $conn->fetchAllAssociative($programsSql);

                foreach ($results as $result) {
                    $val = $result['value'];

                    isset($this->valuesMap[$result['data_id']][$programsFieldName]) ? $this->valuesMap[$result['data_id']][$programsFieldName] .= ', ' . $val : $this->valuesMap[$result['data_id']][$programsFieldName] = $val;
                }
            }
        }

        if (count($this->referralFormsFields)) {

            $shift = 0;

            if ($this->user) {
                $timezone = $this->user->getData()->getTimeZone();
                $timezones = $this->timezones;
                $shift = $timezones[$timezone]['shift'];
            }

            $referralsSql = "SELECT `data_id`, `status`, `comment`, DATE_FORMAT(DATE_ADD(`created_at`, INTERVAL $shift HOUR),'|%m/%d/%Y|%h:%i %p') AS `created_at`, DATE_FORMAT(DATE_ADD(`last_action_at`, INTERVAL $shift HOUR),'|%m/%d/%Y|%h:%i %p') AS `last_action_at`, last_action_user FROM referral WHERE `data_id` IN ($dataIds)";
            $results = $conn->fetchAllAssociative($referralsSql);

            foreach ($results as $result) {

                $lastActionUser = $result['last_action_user'] ? $this->managersNamesMap[$result['last_action_user']] . ' on ' : '';

                if (in_array('_referral_status', $this->referralFormsFields)) {
                    $this->valuesMap[$result['data_id']]['_referral_status'] = str_replace('_', ' ', ucfirst($result['status']));
                }

                if (in_array('_referral_status', $this->referralFormsFields)) {
                    $this->valuesMap[$result['data_id']]['_referral_not_enrolled_reason'] = $result['comment'];
                }

                if (in_array('_referral_timestamp_received', $this->referralFormsFields)) {
                    $this->valuesMap[$result['data_id']]['_referral_timestamp_received'] = $result['created_at'];
                }

                if (in_array('_referral_timestamp_completed', $this->referralFormsFields)) {
                    $this->valuesMap[$result['data_id']]['_referral_timestamp_completed'] = $lastActionUser . $result['last_action_at'];
                }

                if (in_array('_referral_timestamp_not_enrolled', $this->referralFormsFields)) {
                    $this->valuesMap[$result['data_id']]['_referral_timestamp_not_enrolled'] = $result['status'] == ReferralStatus::NOT_ENROLLED ? $lastActionUser . $result['last_action_at'] : '';
                }
            }
        }

        return $this->valuesMap;
    }

    /**
     * Get extra fields values - extra column may be label or value for fields of type: rating, radio-group
     * Get only file names from "file" fields
     * Get manager name from manager fields
     *
     * @return array
     */
    private function getExtraFieldsValues(): array
    {
        $cols = [];

        foreach ($this->extraFieldsMap as $formId => $colNames) {
            $formHelper = new $this->formHelper($this->em);
            $formHelper->setForm($this->reportForms[$formId]);

            foreach ($colNames as $colName) {
                $cols[$colName] = $formHelper->getColumnByName($colName);
            }
        }

        foreach ($this->valuesMap as $formDataId => $formValuesMap) {
            foreach ($formValuesMap as $formDataName => $formDataValue) {
                if (in_array($formDataName, $this->getLabelForFields)) {
                    if (strpos($formDataName, 'rating-') === 0) {
                        $this->valuesMap[$formDataId][$formDataName . '-label'] = $this->formHelper::getLabelForValue($cols[$formDataName], $formDataValue);
                    }

                    if (strpos($formDataName, 'radio-group-') === 0) {
                        $this->valuesMap[$formDataId][$formDataName . '-label'] = $this->valuesMap[$formDataId][$formDataName];
                    }
                }

                if (in_array($formDataName, $this->getValueForFields)) {
                    if (strpos($formDataName, 'rating-') === 0) {
                        $this->valuesMap[$formDataId][$formDataName . '-value'] = $this->valuesMap[$formDataId][$formDataName];
                    }

                    if (strpos($formDataName, 'radio-group-') === 0) {
                        $this->valuesMap[$formDataId][$formDataName . '-value'] = $this->formHelper::getValueForLabel($cols[$formDataName], $formDataValue);
                    }
                }

                if (isset($this->managersValuesMap[$formDataName])) {
                    $formDataValue ? $this->valuesMap[$formDataId][$formDataName] = $this->managersNamesMap[$formDataValue] : '';
                }
            }
        }

        return $this->valuesMap;
    }

    /**
     * Generate results - first stage
     *
     * @return array
     */
    private function generateResults(): array
    {
        $cores = [];
        $merged = [];

        $presentCoreFormsMap = [];

        foreach ($this->formsData as $formData) {
            $formId = $formData['form_id'];
            $formDataId = $formData['id'];
            $participantId = $formData['element_id'];
            $isCore = (bool)in_array($formId, $this->formsMap['core']);
            $assignmentId = $formData['assignment_id'] ?: 0;

            // check if is present but not matching

            if (!in_array($formDataId, $this->matchingFormsDataIds[$formId])) {
                if (isset($this->valuesMap[$formDataId]) && !$isCore) {
                    --$this->participantsFilledFormsCountMap[$participantId][$formId];
                }
                continue;
            }

            if ($isCore) {
                if (!isset($cores[$participantId][$assignmentId])) {
                    $cores[$participantId][$assignmentId] = 0;
                }
                $cores[$participantId][$assignmentId]++;
            }

            $fields = $this->valuesMap[$formDataId];


            $timezone = $this->user->getData()->getTimeZone();
            $timezones = $this->timezones;
            $createdDate = ReportsTransformer::parseDate($formData['created_date_raw'], $timezone, $timezones[$timezone]);

            if (in_array('_creator_id', $this->fieldsNames)) {
                $userName = $formData['creator_id'] ? ($this->managersNamesMap[$formData['creator_id']] ?? '(USER REMOVED)') : 'System Administrator';

                $fields['_creator_id'] = ReportsTransformer::creatorId($userName, $createdDate);
            }

            if (in_array('_editor_id', $this->fieldsNames)) {
                $userName = $formData['editor_id'] ? ($this->managersNamesMap[$formData['editor_id']] ?? '(USER REMOVED)') : 'System Administrator';

                $updatedDate = ReportsTransformer::parseDate($formData['updated_date_raw'], $timezone, $timezones[$timezone]);
                $fields['_editor_id'] = ReportsTransformer::editorId($userName, $createdDate, $updatedDate);
            }

            if (in_array('_manager_id', $this->fieldsNames)) {
                $fields['_manager_id'] = $this->managersNamesMap[$formData['manager_id']] ?? '';
            }

            if (in_array('_secondary_manager_id', $this->fieldsNames)) {
                $fields['_secondary_manager_id'] = $this->managersNamesMap[$formData['secondary_manager_id']] ?? '';
            }

            if (in_array('_casemgr_account', $this->fieldsNames)) {
                $fields['_casemgr_account'] = $this->casemgrAccountsMap[$formData['account_id']] ?? '';
            }

            if (in_array('_organization_name', $this->fieldsNames)) {
                $fields['_organization_name'] = $this->casemgrAccountsMap[$formData['account_id']] ?? '';
            }

            $fields['_forms_data'] = [
                'form_data_id' => $formDataId,
                'element_id' => $participantId,
                'module_id' => $formData['module_id'],
                'assignment_id' => $assignmentId
            ];

            foreach ($fields as $fieldName => $fieldValue) {
                if ($fieldName === '_forms_data') {
                    $this->columns[] = ['field' => $formId . '__forms_data', 'label' => 'forms_data', 'hidden' => true, 'forms_data' => true];
                }
                $fieldName = $formId . '_' . $fieldName;

                if ($isCore) {
                    $merged['core'][$participantId][$assignmentId][$fieldName] = $fieldValue;
                    $presentCoreFormsMap[$participantId][$assignmentId][$formId] = true;
                    continue;
                }

                $merged['multiple'][$participantId][$assignmentId][$formDataId][$fieldName] = $fieldValue;
            }
        }
        $coreFormsCount = count($this->formsMap['core']);

        // filter out core forms if not all of them are matching

        foreach ($presentCoreFormsMap as $participantId => $assignmentFormsCount) {
            foreach ($assignmentFormsCount as $assignmentId => $assignmentForms) {
                if (count($assignmentForms) !== $coreFormsCount) {
                    unset($merged['core'][$participantId][$assignmentId]);
                }
            }
        }
        $merged = $this->unsetParticipantsIfHaveFilledButNotMatchingMultipleForms($merged);
        $merged = $this->clearRowsWithWrongAssignments($cores, $merged);

        return $merged;
    }

    /**
     * @param array $merged
     * @return array
     */
    private function unsetParticipantsIfHaveFilledButNotMatchingMultipleForms(array $merged): array
    {
        $multipleForms = $this->formsMap['multiple'];
        $unsetParticipants = [];

        if (isset($merged['core']) && !empty($multipleForms)) {
            foreach ($merged['core'] as $participantId => $data) {
                $unsetParticipants[$participantId] = 0;

                foreach ($multipleForms as $formId) {
                    if (!isset($this->participantsFilledFormsCountMap[$participantId][$formId])) {
                        continue 2;
                    }
                    $unsetParticipants[$participantId] += $this->participantsFilledFormsCountMap[$participantId][$formId];
                }

                if ($unsetParticipants[$participantId] === 0) {
                    unset($merged['core'][$participantId]);
                }
            }
        }
        return $merged;
    }

    private function clearRowsWithWrongAssignments(array $cores, array $merged): array
    {

        // skip if there are no conditions
        if (count($this->formsMap['core']) && !count(array_intersect($this->formsMap['core'], array_keys($this->conditionsAppliedToFormsMap)))) {
            return $merged;
        }

        foreach ($cores as $participantId => $assigment) {
            foreach ($assigment as $assigmentId => $assignmentCount) {
                // if assignment don't have all core forms - remove it
                if ((count($this->formsMap['core']) !== $assignmentCount) && isset($merged['core'][$participantId][$assigmentId])) {
                    unset($merged['core'][$participantId][$assigmentId]);
                }
            }
        }
        return $merged;
    }

    /**
     * Format results for front, cache, get results count
     *
     * @param array $merged
     * @return array
     */
    private function formatResult(array $merged): array
    {
        $results = [];

        // if there are any multiple forms filled
        if (isset($merged['multiple']) && count($this->formsMap['multiple'])) { // count "multiple" forms and check if they have data

            $assignmentsMap = $merged['multiple'];

            if (count($this->formsMap['core'])) {
                $assignmentsMap = $merged['core'] ?? [];
            }

            foreach ($assignmentsMap as $participantId => $assignment) {
                foreach ($assignment as $assignmentId => $formData) {
                    $allMultipleFormFields = $merged['multiple'][$participantId][$assignmentId] ?? null;

                    if (!$allMultipleFormFields && !isset($this->nullForms['multiple'])) {
                        continue;
                    }

                    if (!$allMultipleFormFields && empty(array_diff($this->formsMap['multiple'], $this->nullForms['multiple']))) {
                        if ($this->unduplicatedConditionSet) {
                            $results[$participantId] = ['_participant_id' => $participantId] + $assignmentsMap[$participantId][$assignmentId];
                            continue 2;
                        }

                        $results[] = ['_participant_id' => $participantId] + $assignmentsMap[$participantId][$assignmentId];
                        continue;
                    }

                    if ($allMultipleFormFields && empty(array_diff($this->formsMap['multiple'], $this->nullForms['multiple']))) {
                        foreach ($allMultipleFormFields as $formFields) {
                            if ($this->unduplicatedConditionSet) {
                                $results[$participantId] = ['_participant_id' => $participantId] + $formFields + $assignmentsMap[$participantId][$assignmentId];
                                continue;
                            }

                            $results[] = ['_participant_id' => $participantId] + $formFields + $assignmentsMap[$participantId][$assignmentId];
                        }
                        continue;
                    }

                    if ($allMultipleFormFields) {
                        $cantBeNull = array_diff($this->formsMap['multiple'], $this->nullForms['multiple']);

                        $dataIds = array_keys($allMultipleFormFields);
                        $formsIds = [];

                        foreach ($dataIds as $dataId) {
                            $formsIds[] = $this->formDataToIdMap[$dataId];
                        }

                        $formsIds = array_unique($formsIds);

                        $missingForms = array_diff($this->formsMap['multiple'], $formsIds); // te sa nullami

                        if (!empty(array_intersect($missingForms, $cantBeNull))) {
                            continue;
                        }

                        foreach ($allMultipleFormFields as $formFields) {
                            if ($this->unduplicatedConditionSet) {
                                $results[$participantId] = $formFields + $assignmentsMap[$participantId][$assignmentId];
                                continue;
                            }

                            $results[] = ['_participant_id' => $participantId] + $formFields + $assignmentsMap[$participantId][$assignmentId];
                        }
                    }
                }
            }
            // process only core forms
            // if there are only core forms, but not data for multiple forms at all - check if any form can be null
        } elseif (
            (isset($merged['core']) && !count($this->formsMap['multiple']))
            || (isset($merged['core']) && (count($this->formsMap['multiple']) && count($this->nullForms['multiple'])))
        ) {
            foreach ($merged['core'] as $participantId => $assignment) {
                foreach ($assignment as $assignmentId => $formData) {
                    if ($this->unduplicatedConditionSet) {
                        $results[$participantId] = ['_participant_id' => $participantId] + $merged['core'][$participantId][$assignmentId];
                        continue;
                    }
                    $results[] = ['_participant_id' => $participantId] + $merged['core'][$participantId][$assignmentId];
                }
            }
        }

        $allResults = [];

        foreach ($results as $rowIdx => $result) {
            foreach ($this->columns as $col) {

//                decided to not show this type of field for now
//                $result = $this->addSharedFormsPreviewsFields($col['field'], $result);

                if (!isset($result[$col['field']])) {
                    $allResults[$rowIdx][$col['field']] = '';
                    continue;
                }

                $allResults[$rowIdx][$col['field']] = $result[$col['field']];
            }
        }

        $this->resultsCount += count($allResults);

        return $allResults;
    }

    private function saveReportToCache($results): void
    {
        $conn = $this->reportsEm->getConnection();
        $conn->setAutoCommit(false);
        $conn->beginTransaction();

        $tableName = 'report_' . $this->report->getId() . '_' . $this->user->getId();

        $cols = [];

        foreach ($this->columns as $column) {
            $cols[] = $column['field'];
        }

        $cols = array_unique($cols);

        $colsStr = '';
        $insertToColsStr = '';

        foreach ($cols as $cname) {
            $colsStr .= "`$cname` TEXT,";
            $insertToColsStr .= "`$cname`,";
        }

        $colsStr = rtrim($colsStr, ',');
        $insertToColsStr = rtrim($insertToColsStr, ',');

        if ($this->currentChunk === 0) {

            $tables = $conn->getSchemaManager()->listTableNames();
            $reportId = $this->report->getId();

            foreach ($tables as $table) {
                if (strpos($table, 'report_' . $reportId) === 0) {
                    $conn->exec("DROP TABLE IF EXISTS $table");
                }
            }

            $sql = "CREATE TABLE $tableName (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL, $colsStr CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci)";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
        } else {
            $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '".$tableName."'";
            $currentColumnsRes = $conn->fetchAllAssociative($sql);
            $currentColumns = array_map(static fn($item) => $item['COLUMN_NAME'], $currentColumnsRes);

            foreach ($cols as $col){
                if (!in_array($col, $currentColumns, true)){
                    $sql = "ALTER TABLE $tableName ADD `$col` TEXT";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute();
                }
            }
        }

        $conn->setAutoCommit(false);

        $conn->beginTransaction();

        $chunks = array_chunk($results, 200, true);

        foreach ($chunks as $chunkIdx => $chunk) {
            $values = '';

            foreach ($chunk as $rowId => $formValues) {
                $oneRowStr = '';

                foreach ($formValues as $colValue) {
                    if (is_array($colValue)) {
                        $colValue = json_encode($colValue, JSON_THROW_ON_ERROR);
                    } else {
                        $colValue = addslashes($colValue);
                    }
                    $oneRowStr .= '\'' . $colValue . '\',';
                }

                $oneRowStr = rtrim($oneRowStr, ',');
                $values .= '(' . $oneRowStr . '),';
            }

            $values = rtrim($values, ',');

            $sql = "INSERT INTO $tableName ($insertToColsStr) VALUES $values";
            $stmt = $conn->prepare($sql);
            $stmt->execute();
        }

        $conn->commit();
    }

    private function UpdateReport() {
        $conn = $this->reportsEm->getConnection();
        $tableName = 'report_' . $this->report->getId() . '_' . $this->user->getId();
        $sql = "SELECT MAX(id) FROM $tableName";
        $statement = $conn->prepare($sql);
        $results = $statement->execute();
        $count = (int) $results->fetchFirstColumn()[0];

        $this->report->setResultsCount($count);
        $this->report->setDateFormat($this->dateFormat);
        $this->em->flush();

        $this->report->clearForms();

        foreach ($this->reportForms as $form) {
            $reportsForm = new ReportsForms();
            $reportsForm->setForm($form);
            $reportsForm->setReport($this->report);
            $reportsForm->setInvalidatedAt(null);
            $this->em->persist($reportsForm);
        }

        $this->em->flush();
    }

    private function addSharedFormsPreviewsFields($field, $result)
    {
        if (strpos($field, 'shared-form-preview')) {
            $columnData = explode('_', $field);
            $formId = $columnData[0];
            $fieldName = $columnData[1];

            /** @var Forms $form */
            $form = $this->em->getRepository('App:Forms')->find($formId);

            $sharedForm = $this->sharedFormPreviewsHelper->getFormPreviews($form, $form->getAccounts()->first(), $result['_participant_id']);

            $data = [];
            foreach ($sharedForm[$fieldName]['rows'] as $row) {
                $item = [];
                foreach ($sharedForm[$fieldName]['columns'] as $column) {
                    $item[$column['label']] = $row[$column['field']];
                }
                $data[] = $item;
            }

            $result[$field] = $data;
        }
        return $result;
    }
}
