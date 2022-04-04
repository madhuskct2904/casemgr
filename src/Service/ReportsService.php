<?php

namespace App\Service;

use App\Domain\Reports\ReportSummaryService;
use App\Entity\Accounts;
use App\Entity\Reports;
use App\Entity\ReportsForms;
use App\Entity\Users;
use App\Entity\UsersSettings;
use App\Enum\AccountType;
use App\Enum\ReportMode;
use App\Service\S3ClientFactory;
use Aws\S3\Exception\S3Exception;
use App\Library\Box\Spout\Common\Type;
use App\Library\Box\Spout\Writer\Style\Style;
use App\Library\Box\Spout\Writer\Style\StyleBuilder;
use App\Library\Box\Spout\Writer\WriterFactory;
use App\Library\Box\Spout\Writer\WriterInterface;
use App\Library\Box\Spout\Writer\XLSX\Writer;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use function Sentry\captureException;

class ReportsService
{
    protected $em;
    protected $reportsEm;
    protected $reportGenerator;
    protected $reportSummaryService;
    protected $projectDir;
    protected $dateFormat = 'm/d/Y';
    protected $user = null;
    protected $timezones;
    protected $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        EntityManagerInterface $reportsCacheEntityManager,
        ReportGenerator $reportGenerator,
        ReportSummaryService $reportSummaryService,
        S3ClientFactory $s3ClientFactory,
        LoggerInterface $logger,
        $s3BucketName,
        $projectDir
    ) {
        $this->em = $entityManager;
        $this->reportsEm = $reportsCacheEntityManager;
        $this->reportGenerator = $reportGenerator;
        $this->reportSummaryService = $reportSummaryService;
        $this->s3Client = $s3ClientFactory->getClient();
        $this->s3BucketName = $s3BucketName;
        $this->projectDir = $projectDir;
        $this->logger = $logger;
    }

    public function setDateFormat($dateFormat)
    {
        $this->dateFormat = $dateFormat;
    }

    public function setTimeZones($timezones)
    {
        $this->timezones = $timezones;
    }

    public function getTimeZones()
    {
        return $this->timezones;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function setUser(?Users $user): void
    {
        $this->user = $user;
    }


    public function isValidReport(Reports $report): bool
    {
        if ($report->getDateFormat() !== $this->dateFormat) {
            $this->removeOldFiles($report);
            return false;
        }

        $formsIdsInReportData = array_column(json_decode($report->getData(), true), 'form_id');
        $reportId = $report->getId();

        $result = $this->em->createQuery("SELECT IDENTITY(a.form) FROM App\Entity\ReportsForms a WHERE a.report = $reportId")->getScalarResult();
        $formIdsInDb = array_column($result, 1);

        $missingForms = array_diff($formsIdsInReportData, $formIdsInDb);

        if (count($missingForms)) {
            foreach ($missingForms as $missingFormId) {
                $form = $this->em->getRepository('App:Forms')->find($missingFormId);
                if ($form) {
                    $reportsForm = new ReportsForms();
                    $reportsForm->setForm($form);
                    $reportsForm->setReport($report);
                    $reportsForm->setInvalidatedAt(new DateTime());
                    $this->em->persist($reportsForm);
                }
            }
            $this->em->flush();
            $this->removeOldFiles($report);
            return false;
        }

        $invalidatedForms = $this->em->getRepository('App:ReportsForms')->getInvalidatedForms($report->getId());

        if (count($invalidatedForms)) {
            $this->removeOldFiles($report);
            return false;
        }

        $schemaManager = $this->reportsEm->getConnection()->getSchemaManager();
        $tableName = 'report_' . $report->getId() . '_' . $this->user->getId();

        if (!$schemaManager->tablesExist([$tableName])) {
            $this->removeOldFiles($report);
            return false;
        }

        return true;
    }

    public function getPreview($report): array
    {
        $this->reportGenerator->setDateFormat($this->dateFormat);
        $this->reportGenerator->setUser($this->user);
        $this->reportGenerator->setTimeZones($this->timezones);
        return $this->reportGenerator->generateReport($report, true, 10);
    }

    public function getResults($report, $offset, $limit, $sortBy, $sortDir, $reportReady = null): array
    {
        if ($reportReady === null) { // check if we don't have information if report is ready or not
            $reportReady = $this->isValidReport($report);
        }

        if ($reportReady) {
            return $this->getResultsFromCache($report, $offset, $limit, $sortBy, $sortDir);
        }

        $this->reportGenerator->setDateFormat($this->dateFormat);
        $this->reportGenerator->setUser($this->user);
        $this->reportGenerator->setTimeZones($this->timezones);

        $this->reportGenerator->generateReport($report);

        return $this->getResultsFromCache($report, $offset, $limit, $sortBy, $sortDir);
    }

    public function generateCsv(
        Reports $report,
        array $dataToExport,
        string $title,
        int $rowsInChunk,
        int $chunk
    ): array
    {
        $reportReady = $this->isValidReport($report);
        $filename = md5('report' . $report->getId() . 'csv');
        $finalFileName = md5('final_report' . $report->getId() . 'csv');
        $fileDirectory = $this->projectDir . '/var/reports/';

        // Create the directory if it doesn't exist
        if (!file_exists($fileDirectory)) {
            mkdir($fileDirectory, 0777, true);
        }

        $filePath = $fileDirectory . $filename;
        $finalFilePath = $this->projectDir . '/var/reports/' . $finalFileName;

        $result = $this->getResults($report, $chunk * $rowsInChunk, $rowsInChunk, false, false, $reportReady);
        $result = $this->filterResultsForExport($result);

        $lastChunk = false;

        if ($result['count'] <= $rowsInChunk * ($chunk + 1)) {
            $lastChunk = true;
        }


        $finalColumns = $this->getFinalColumns($result);
        $data = $this->getFinalData($result, $finalColumns);

        if ($chunk === 0) {
            $file = fopen($filePath, 'w');

            fputcsv($file, [$title]);
            fputcsv($file, ['']);
            fputcsv($file, $finalColumns['labels']);

            foreach ($data as $row) {
                fputcsv($file, array_values($row));
            }
        }

        if ($chunk > 0) {
            $file = fopen($filePath, 'a');
            foreach ($data as $row) {
                fputcsv($file, array_values($row));
            }
        }

        if ($lastChunk) {
            $filesystem = new Filesystem();
            $filesystem->rename($filePath, $finalFilePath, true);

            try {
                $this->s3Client->putObject([
                    'Bucket'     => $this->s3BucketName,
                    'Key'        => 'reports_exports/' . $finalFileName,
                    'SourceFile' => $finalFilePath,
                ]);
            } catch (S3Exception $e) {
                $this->logger->error($e->getMessage());
                $this->logger->error($e->getTraceAsString());
                captureException($e); // capture exception by Sentry

                return [
                    'next_chunk'    => 0,
                    'rows_in_chunk' => 0,
                    'error'         => true,
                    'url'           => null
                ];
            }

            unlink($finalFilePath);

            return [
                'next_chunk'    => 0,
                'rows_in_chunk' => $rowsInChunk,
                'url'           => 'reports/download/csv/' . $report->getId()
            ];
        }

        return [
            'next_chunk'    => $chunk + 1,
            'rows_in_chunk' => $rowsInChunk,
            'url'           => ''
        ];
    }


    public function generateXlsx(Reports $report, array $dataToExport, string $title): array
    {
        $reportReady = $this->isValidReport($report);
        $filename = md5('final_report' . $report->getId() . 'xlsx');
        $fileDirectory = $this->projectDir . '/var/reports/';

        // Create the directory if it doesn't exist
        if (!file_exists($fileDirectory)) {
            mkdir($fileDirectory, 0777, true);
        }

        $filePath = $fileDirectory . $filename;
        $sheetNames = ['Results'];

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $numberFormat = strtoupper($this->dateFormat);
        $numberFormat = str_replace(['M', 'D', 'Y'], ['MM', 'DD', 'YYYY'], $numberFormat);

        $styleDate = (new StyleBuilder())->setNumberFormat($numberFormat)->build();

        $sheetIndex = 0;

        $writer = null;

        if (!empty($dataToExport['results'])) {

            $result = $this->getResults($report, 0, PHP_INT_MAX, false, false, $reportReady);
            $result = $this->filterResultsForExport($result);

            $writer = $this->initXlsWriter($filePath, [$styleDate]);

            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Results');
            $sheetIndex++;

            $finalColumns = $this->getFinalColumns($result);

            $data = $this->getFinalData($result, $finalColumns);

            $writer->addRow([$title]);
            $writer->addRow([' ']);
            $writer->addRow($finalColumns['labels']);

            $this->writeRowsToXls($result['columns'], $data, $styleDate, $writer);

        }

        if (!empty($dataToExport['summary'])) {

            $service = $this->reportSummaryService;
            $service->setUser($this->user);
            $summaryIndex = $service->getIndex($report->getId());

            if (count($summaryIndex)) {

                if (!$writer instanceof Writer) {
                    $writer = $this->initXlsWriter($filePath, [$styleDate]);
                }

                foreach ($summaryIndex as $summary) {
                    $i = 0;

                    if ($sheetIndex > 0) {
                        $writer->addNewSheetAndMakeItCurrent();
                    }

                    $sheet = $writer->getCurrentSheet();

                    $baseSheetName = substr(str_replace(['*', '/', '?', ':', '[', ']'], ['', '', '', '', '-', '-'], $summary['name']), 0, 31);
                    $sheetName = $baseSheetName;

                    while (in_array($sheetName, $sheetNames)) {
                        $i++;
                        $suffix = '(' . $i . ')';
                        $sheetName = substr($baseSheetName, 0, 31 - strlen($suffix)) . $suffix;
                    }

                    $sheetNames[] = $sheetName;
                    $sheet->setName($sheetName);
                    $sheetIndex++;

                    $writer->addRow([$summary['name']]);
                    $writer->addRow([' ']);
                    $writer->addRow(array_column($summary['columns'], 'label'));

                    $rows       = $summary['rows'];
                    $parsedRows = [];

                    foreach ($rows as $rowIdx => $summaryRow) {
                        foreach ($summary['columns'] as $col) {
                            if (isset($summaryRow[$col['field']])) {
                                $parsedRows[$rowIdx][$col['field']] = $summaryRow[$col['field']];
                            }
                        }
                    }

                    $this->writeRowsToXls($summary['columns'], $parsedRows, $styleDate, $writer);
                }
            }
        }

        if ($writer instanceof Writer) {
            $writer->close();
        }

        try {
            $this->s3Client->putObject([
                'Bucket'     => $this->s3BucketName,
                'Key'        => 'reports_exports/' . $filename,
                'SourceFile' => $filePath,
            ]);
        } catch (S3Exception $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());
            captureException($e); // capture exception by Sentry

            return [
                'next_chunk'    => 0,
                'rows_in_chunk' => 0,
                'error'         => true,
                'url'           => null
            ];
        }

        unlink($filePath);

        return [
            'next_chunk'    => 0,
            'rows_in_chunk' => 0,
            'error'         => false,
            'url'           => 'reports/download/xlsx/' . $report->getId()
        ];
    }

    private function getFinalData(array $result, array $finalColumns): array
    {
        $data = [];
        foreach ($result['results'] as $res){
            $row = [];
            foreach ($finalColumns['columns'] as $finalColumn){
                if (is_array($finalColumn)){
                    $value = $res[$finalColumn['field']];
                    if (is_array($value) === false) {
                        $value = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
                    }

                    $row[$finalColumn['name']] = $value[$finalColumn['row']][$finalColumn['label']] ?? '';
                } else {
                    if (is_array($res[$finalColumn]) === true){
                        continue;
                    }
                    $row[$finalColumn] = $res[$finalColumn] ?? null;
                }
            }
            $data[] = $row;
        }

        return $data;
    }

    private function getFinalColumns(array $result): array
    {
        $columns = [];
        $columnKeys = [];
        foreach($result['columns'] as $column){
            if (strpos($column['field'], 'shared-form-preview') !== false){
                $data = $result['results'][0][$column['field']];
                if (is_array($data) === false) {
                    $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                }
                foreach ($data as $element){
                    $columns = array_merge($columns, array_keys($element));
                    $columnKeys = array_merge($columnKeys, array_keys($element));
                }
            } else {
                $columns[] = $column['label'];
                $columnKeys[] = $column['field'];
            }
        }

        $newColumns = [];
        $newColumnsLabels = [];
        foreach ($result['results'] as $res){
            $item = [];
            $itemLabels = [];
            foreach ($res as $key => $data){
                if (strpos($key, 'shared-form-preview') !== false){
                    if (is_array($data) === false) {
                        $data = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                    }
                    foreach ($data as $dkey => $element){
                        $i = 0;
                        foreach ($element as $eKey => $value){
                            $item[] = ['field' => $key, 'label' => $eKey, 'index' => $i, 'row' => $dkey, 'name' => $key.'__'.$dkey.'__'.$i];
                            ++$i;
                            $itemLabels[] = $eKey;
                        }
                    }
                } else {
                    $item[] = $key;
                    foreach ($result['columns'] as $column){
                        if ($column['field'] === $key){
                            $itemLabels[] = $column['label'];
                        }
                    }
                }
            }
            $newColumns[] = $item;
            $newColumnsLabels[] = $itemLabels;
        }
        array_multisort(array_map('count', $newColumns), SORT_DESC, $newColumns);
        array_multisort(array_map('count', $newColumnsLabels), SORT_DESC, $newColumnsLabels);
        if (isset($newColumns[0])) {
            $finalColumns = $newColumns[0];
            $finalLabels = $newColumnsLabels[0];
        } else {
            $finalColumns = $columnKeys;
            $finalLabels = $columns;
        }

        return [
            'columns' => $finalColumns,
            'labels' => $finalLabels
        ];
    }

    public function getResultsFromCache($report, $offset = 0, $limit = 10, $sortBy = false, $sortOrder = 'ASC')
    {
        $columns = $this->getReportColumns($report);

        $tableName = 'report_' . $report->getId() . '_' . $this->user->getId();

        $sortStr = '';

        if ($sortBy) {
            $sortOrder = in_array($sortOrder, ['ASC', 'DESC']) ? $sortOrder : 'ASC';

            if (strstr($sortBy, '_currency') !== false) {
                $sortBy = "CAST(REPLACE(REPLACE(`$sortBy`, '$', ''),',','') AS DECIMAL(10,4)) ";
            } elseif (strstr($sortBy, '_number') !== false) {
                $sortBy = "CAST(`$sortBy` AS DECIMAL(10,4)) ";
            } elseif (strstr($sortBy, '_date') !== false) {
                $sortBy = "STR_TO_DATE(`$sortBy`, '%m/%d/%Y') ";
            } else {
                $sortBy = "`$sortBy`";
            }

            $sortStr = "ORDER BY $sortBy $sortOrder";
        }

        $limitStr = '';

        if ($limit) {
            $limitStr = "LIMIT $limit OFFSET $offset";
        }

        $sql = "SELECT * FROM $tableName $sortStr $limitStr";

        $results = $this->reportsEm->getConnection()->fetchAllAssociative($sql);

        foreach($results as $key => $result){
            unset($results[$key]['id']);
            unset($results[$key]['_participant_id']);
            foreach ($result as $fieldKey => $fieldValue){
                if (strpos($fieldKey, '__forms_data') !== false){
                    if ($fieldValue === ""){
                        $results[$key][$fieldKey] = "";
                    } else {
                        $results[$key][$fieldKey] = json_decode($fieldValue, true, 512, JSON_THROW_ON_ERROR);
                    }
                }

                if (strpos($fieldKey, '_shared-form-preview') !== false){
                    if ($fieldValue === ""){
                        $results[$key][$fieldKey] = "";
                    } else {
                        $results[$key][$fieldKey] = json_decode($fieldValue, true, 512, JSON_THROW_ON_ERROR);
                    }
                }
            }
        }

        return [
            'results'   => $results,
            'columns'   => $columns,
            'count'     => $report->getResultsCount(),
            'isPreview' => false
        ];
    }

    /**
     * @param $report
     * @return array
     */
    public function getReportColumns(Reports $report): array
    {
        $data = json_decode($report->getData(), true);
        $columns = [];

        foreach ($data as $reportData) {
            $formId = $reportData['form_id'];

            foreach ($reportData['fields'] as $field) {
                $fieldLabel = $field['label'];
                if (isset($field['columns'])) {
                    if (in_array('label', $field['columns'])) {
                        $columns[] = [
                            'field' => $formId . '_' . $field['field'] . '-label',
                            'label' => count($field['columns']) > 1 ? $fieldLabel . ' Label' : $fieldLabel
                        ];
                    }

                    if (in_array('value', $field['columns'])) {
                        $columns[] = [
                            'field' => $formId . '_' . $field['field'] . '-value',
                            'label' => count($field['columns']) > 1 ? $fieldLabel . ' Value' : $fieldLabel
                        ];
                    }
                    continue;
                }

                $columns[] = [
                    'field' => $formId . '_' . $field['field'],
                    'label' => $fieldLabel
                ];
            }
        }
        return $columns;
    }

    public function saveTopReports($user, $account, $topReportsIds)
    {
        $settings = $this->em->getRepository('App:UsersSettings')->findOneBy([
            'user' => $user,
            'name' => 'top_reports_account_' . $account->getId()
        ]);

        if (!$settings) {
            $settings = new UsersSettings();
        }

        $settings->setUser($user);
        $settings->setName('top_reports_account_' . $account->getId());
        $settings->setValue(json_encode($topReportsIds));
        $this->em->persist($settings);
        $this->em->flush();
    }

    public function removeOldFiles($report)
    {
        $filePath = $this->projectDir . '/var/reports/';
        $files = [
            $filePath . md5('report' . $report->getId()) . '.xlsx',
            $filePath . md5('final_report' . $report->getId() . 'xlsx'),
            $filePath . md5('report' . $report->getId() . 'csv'),
            $filePath . md5('final_report' . $report->getId() . 'csv')
        ];

        $filesystem = new Filesystem();
        $filesystem->remove($files);
    }

    public function updateChildReports(Reports $parentReport, Accounts $parentAccount, Users $user, array $oldReportAccounts = []): void
    {
        $em = $this->em;

        if (!$parentAccount->getAccountType() == AccountType::PARENT) {
            throw new \Exception('Security violation! Account is not a parent account!');
        }

        $accounts = json_decode($parentReport->getAccounts(), true);
        $accountsIds = array_column($accounts, 'id');

        if (!count($accountsIds)) {
            $childAccounts = $parentAccount->getChildrenAccounts();
        } else {
            $childAccounts = $em->getRepository('App:Accounts')->findBy(['id' => array_diff($accountsIds, [$parentAccount->getId()])]);
        }

        if (!in_array($parentReport->getMode(), [ReportMode::SINGLE_MIRROR_PARENT, ReportMode::MULTIPLE_MIRROR_PARENT])) {
            $childReports = $em->getRepository('App:Reports')->findBy(['parentId' => $parentReport]);
            foreach ($childReports as $childReport) {
                $em->remove($childReport);
                $em->flush();
            }
            return;
        }


        if (!count($oldReportAccounts) && count($accountsIds)) {
            $accounts = $parentAccount->getChildrenAccounts();
            foreach ($accounts as $oldReportAccount) {
                if (!in_array($oldReportAccount->getId(), $accountsIds)) {
                    $childReport = $em->getRepository('App:Reports')->findOneBy([
                        'parentId' => $parentReport,
                        'account'  => $oldReportAccount
                    ]);

                    if ($childReport) {
                        $em->remove($childReport);
                        $em->flush();
                    }
                }
            }
        }

        if (count($oldReportAccounts) && count($accountsIds)) {
            $oldReportAccounts = array_column($oldReportAccounts, 'id');
            $removeReportFromAccounts = array_diff($oldReportAccounts, $accountsIds);
            foreach ($removeReportFromAccounts as $accountId) {
                $account = $em->getRepository('App:Accounts')->find($accountId);
                $removeReport = $em->getRepository('App:Reports')->findBy(
                    [
                        'parentId' => $parentReport,
                        'accounts' => $account
                    ]
                );
                $em->remove($removeReport);
                $em->flush();
            }
        }

        $parentFolder = $parentReport->getFolder();

        foreach ($childAccounts as $childAccount) {
            if ($childAccount->getParentAccount() != $parentAccount) {
                throw new \Exception('Security violation! Account is not a child account of selected parent!');
            }

            $childReport = $em->getRepository('App:Reports')->findOneBy(
                [
                    'parentId' => $parentReport,
                    'account'  => $childAccount
                ]
            );

            if (!$childReport) {
                $childReport = new Reports();
                $rootChildFolder = $childFolder = $em->getRepository('App:ReportFolder')->findOneBy(['name' => 'account' . $childAccount->getId()]);
                $childFolder = $em->getRepository('App:ReportFolder')->findOneBy(
                    [
                        'parent' => $rootChildFolder,
                        'name'   => $parentFolder->getName()
                    ]
                );
                $childReport->setFolder($childFolder ?? $rootChildFolder);
            }

            $childReport->setName($parentReport->getName());
            $childReport->setDescription($parentReport->getDescription());
            $childReport->setCreatedDate(new DateTime());
            $childReport->setUser($user);
            $childReport->setData($parentReport->getData());
            $childReport->setType($parentReport->getType());
            $childReport->setAccount($childAccount);
            $childReport->setAccounts('[]');
            $childReport->setMode(ReportMode::MIRROR_CHILD);
            $childReport->setDateFormat($this->dateFormat);
            $childReport->setParentId($parentReport);

            $em->persist($childReport);
            $em->flush();

            $em->getRepository('App:ReportsForms')->invalidateReport($childReport);
        }
    }

    private function filterResultsForExport($result)
    {
        $filesCols = [];
        $specialDates = [];
        $dates = [];
        $referralTimestamps = [];

        $cols = array_column($result['columns'], 'field');

        foreach ($cols as $col) {
            if (strpos($col, '_file-') !== false) {
                $filesCols[] = $col;
            }
            if (strpos($col, '__creator_id') !== false || strpos($col, '__editor_id') !== false) {
                $specialDates[] = $col;
            }
            if (strpos($col, '_date-') !== false) {
                $dates[] = $col;
            }
            if (strpos($col, '_referral_timestamp_') !== false) {
                $referralTimestamps[] = $col;
            }
        }

        if (!count($filesCols) && !count($specialDates) && !count($dates)) {
            return $result;
        }

        $newResults = [];

        foreach ($result['results'] as $row) {
            $newRow = [];
            foreach ($row as $colIdx => $value) {
                if (strpos($colIdx, '__forms_data') !== false || $colIdx === 'id' || $colIdx === '_participant_id'){
                    continue;
                }
                $newValue = $value;
                if (in_array($colIdx, $filesCols)) {
                    $arr = json_decode($value, true);
                    $newValue = '';
                    if ($value !== '' && json_last_error() == JSON_ERROR_NONE) {
                        foreach ($arr as $file) {
                            $newValue .= $file['name'] . ', ';
                        }
                        $newValue = rtrim($newValue, ', ');
                    }
                }

                if (in_array($colIdx, $specialDates) && $value != '') {
                    $value = explode('|', $value);
                    $date  = isset($value[1]) ? DateTime::createFromFormat('m/d/Y', $value[1]) : null;

                    if (null !== $date) {
                        $date = $date->format($this->dateFormat);
                    }

                    $newValue = $value[0] . $date;
                }

                if (in_array($colIdx, $dates) && $value != '') {
                    $date = DateTime::createFromFormat('m/d/Y', $value);
                    if ($date) {
                        $date = $date->format($this->dateFormat);
                    }
                    $newValue = $date;
                }

                if (in_array($colIdx, $referralTimestamps) && $value != '') {
                    $value = explode('|', $value);
                    $date = DateTime::createFromFormat('m/d/Y', $value[1]);
                    if ($date) {
                        $date = $date->format($this->dateFormat);
                        $time = $value[2];
                    }
                    $newValue = $value[0] . $date . ' ' . $time;
                }

                $newRow[$colIdx] = $newValue;
            }
            $newResults[] = $newRow;
        }

        $result['results'] = $newResults;

        return $result;
    }

    protected function writeRowsToXls(array $columns, array $results, Style $styleDate, WriterInterface $writer): void
    {
        $dateCols = [];

        foreach ($columns as $col) {
            if (strpos($col['field'], '_date-') !== false) {
                $dateCols[] = $col['field'];
            }
        }

        foreach ($results as $row) {
            $newRow = [];

            foreach ($row as $rowKey => $rowValue){
                $colIdx = $rowKey;
                $colValue = $rowValue;

                if (in_array($colIdx, $dateCols)) {
                    $date = DateTime::createFromFormat($this->dateFormat, $colValue);

                    if ($date) {
                        $date->setTime(0, 0, 0);
                        $timestamp = $date->format('U');
                        $newRow[] = [25569 + ($timestamp / 86400), $styleDate];
                        continue;
                    }

                    $newRow[] = '';
                } else {
                    $newRow[] = $colValue;
                }
            }

            $writer->addRow($newRow);
        }
    }

    /**
     * @param string $filePath
     * @return \App\Library\Box\Spout\Writer\CSV\Writer|\App\Library\Box\Spout\Writer\ODS\Writer|WriterInterface|\App\Library\Box\Spout\Writer\XLSX\Writer
     * @throws \App\Library\Box\Spout\Common\Exception\IOException
     * @throws \App\Library\Box\Spout\Common\Exception\UnsupportedTypeException
     * @throws \App\Library\Box\Spout\Writer\Exception\WriterAlreadyOpenedException
     */
    private function initXlsWriter(string $filePath, array $styles = [])
    {
        $writer = WriterFactory::create(Type::XLSX);
        $writer->setShouldCreateNewSheetsAutomatically(true);
        $writer->openToFile($filePath);

        foreach ($styles as $style) {
            $writer->registerStyle($style);
        }

        return $writer;
    }


}
