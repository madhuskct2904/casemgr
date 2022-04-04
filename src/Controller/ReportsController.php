<?php

namespace App\Controller;

use App\Entity\Reports;
use App\Entity\Users;
use App\Enum\AccountType;
use App\Enum\ReportMode;
use App\Exception\ExceptionMessage;
use App\Service\ReportsFoldersService;
use App\Service\ReportGenerator;
use App\Service\ReportsService;
use App\Service\S3ClientFactory;
use Aws\S3\Exception\S3Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function Sentry\captureException;

/**
 * Class ReportsController
 *
 * @IgnoreAnnotation("api")
 * @IgnoreAnnotation("apiGroup")
 * @IgnoreAnnotation("apiHeader")
 * @IgnoreAnnotation("apiParam")
 * @IgnoreAnnotation("apiSuccess")
 * @IgnoreAnnotation("apiError")
 *
 * @package App\Controller
 */
class ReportsController extends Controller
{
    /**
     * @return JsonResponse
     * @api {post} /reports Get all Reports
     * @apiGroup Reports
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String} type Report Type
     *
     * @apiSuccess {Array} data Results
     *
     * @apiError message Error Message
     *
     */
    public function indexAction(): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $folderId = $this->getRequest()->param('folder', false);

        if (!$folderId) {
            $folder = $this->getDoctrine()->getRepository('App:ReportFolder')->findOneBy(['name' => 'account' . $this->account()->getId()]);
        } else {
            $folder = $this->getDoctrine()->getRepository('App:ReportFolder')->findOneBy(['id' => $folderId]);
        }

        $type = $this->getRequest()->param('type');
        $findBy = [
            'type'    => $type,
            'account' => $this->account(),
            'folder'  => $folder
        ];

        if ($this->access() < Users::ACCESS_LEVELS['SUPERVISOR']) {
            $findBy['status'] = 1;
        }

        $foldersRepository = $this->getDoctrine()->getRepository('App:ReportFolder');
        $folders = $foldersRepository->children($folder, true, 'name');

        $reports = $this->getDoctrine()->getRepository('App:Reports')->findBy($findBy, ['name' => 'ASC']);

        $reports = $this->formatReportsList($reports);

        $subfolders = [];

        $parent = $folder->getParent();

        $parentFolder = null;

        if ($parent) {
            $parentFolder = ['name' => $parent->getName(), 'id' => $parent->getId()];
        }

        foreach ($folders as $subfolder) {
            $subfolders[] = ['id' => $subfolder->getId(), 'name' => $subfolder->getName()];
        }

        $folderParents = [];
        $path = $foldersRepository->getPath($folder);

        foreach ($path as $parent) {
            $folderParents[] = ['name' => $parent->getName(), 'id' => $parent->getId()];
        }

        $user = $this->user();
        $account = $this->account();
        $topReportsIds = [];

        $topReportsSettings = $this->getDoctrine()->getRepository('App:UsersSettings')->findOneBy([
            'user' => $user,
            'name' => 'top_reports_account_' . $account->getId()
        ]);


        if ($topReportsSettings) {
            $topReportsIds = json_decode($topReportsSettings->getValue(), true);
        }

        return $this->getResponse()->success([
            'reports'         => $reports,
            'folder'          => ['id' => $folder->getId(), 'name' => $folder->getName()],
            'folders'         => $subfolders,
            'path'            => $folderParents,
            'parentFolder'    => $parentFolder,
            'count'           => count($reports),
            'top_reports_ids' => $topReportsIds
        ]);
    }

    /**
     * @return JsonResponse
     * @api {post} /reports/delete Delete Report
     * @apiGroup Reports
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} id Report Id
     *
     * @apiError message Error Message
     *
     */
    public function deleteAction(): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['SUPERVISOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $id = (int)$this->getRequest()->param('id');
        $report = $this->getDoctrine()->getRepository('App:Reports')->find($id);

        if ($report->getParentId() && $this->account()->getAccountType() == AccountType::CHILD) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS_CHILD_ACCOUNT);
        }

        if ($report === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_REPORT_ID);
        }

        $em = $this->getDoctrine()->getManager();

        $deletedIds = [$id];

        if ($report->getMode() == ReportMode::MULTIPLE_MIRROR_PARENT || $report->getMode() == ReportMode::SINGLE_MIRROR_PARENT) {
            $reports = $em->getRepository('App:Reports')->findBy(['parentId' => $id]);

            foreach ($reports as $childReport) {
                $deletedIds[] = $childReport->getId();
                $em->remove($childReport);
            }
        }

        $em->remove($report);
        $em->flush();

        $em->getRepository('App:UsersSettings')->deleteFromTopReports($deletedIds);

        return $this->getResponse()->success();
    }

    /**
     * @return JsonResponse
     * @api {post} /reports/status Toggle Report Status
     * @apiGroup Reports
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} id Report Id
     *
     * @apiError message Error Message
     *
     */
    public function statusAction(): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['SUPERVISOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $id = (int)$this->getRequest()->param('id');
        $report = $this->getDoctrine()->getRepository('App:Reports')->find($id);

        if ($report === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_REPORT_ID);
        }

        $em = $this->getDoctrine()->getManager();

        if ($report->getStatus() === 0) {
            $report->setStatus(1);
        } else {
            $report->setStatus(0);
        }

        $em->persist($report);
        $em->flush();

        return $this->getResponse()->success();
    }

    /**
     * @return JsonResponse
     * @api {post} /reports/duplicate Duplicate Report
     * @apiGroup Reports
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} id Report Id
     * @apiParam {String} name Report Name
     * @apiParam {String} description Report Description
     *
     * @apiError message Error Message
     *
     */
    public function duplicateAction(): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['SUPERVISOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $id = (int)$this->getRequest()->param('id');
        $name = $this->getRequest()->param('name');
        $description = $this->getRequest()->param('description');

        $report = $this->getDoctrine()->getRepository('App:Reports')->find($id);

        if ($report === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_REPORT_ID);
        }

        $mode = $report->getMode();

        $report = clone $report;

        $report->setId(null);
        $report->setParentId(null);
        $report->setName($name);
        $report->setDescription($description);
        $report->setCreatedDate(new \DateTime());
        $report->setUser($this->user());
        $report->setResultsCount(null);

        if ($this->account()->getAccountType() == AccountType::CHILD) {
            $mode = ReportMode::SINGLE;
        }

        $report->setMode($mode);

        $em = $this->getDoctrine()->getManager();

        $em->persist($report);
        $em->flush();

        return $this->getResponse()->success();
    }

    /**
     * @return JsonResponse
     * @api {post} /reports/add Create Report
     * @apiGroup Reports
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String} name Report Name
     * @apiParam {String} description Report Description
     * @apiParam {Array} data Report Data
     * @apiParam {String} [type] Report Type
     *
     * @apiSuccess {Integer} id Report Id
     * @apiSuccess {String} name Report Name
     * @apiSuccess {String} description Report Description
     * @apiSuccess {String} user User Full Name
     * @apiSuccess {Integer} user_id User Id
     * @apiSuccess {String} created_date Report Created Date
     * @apiSuccess {String} status Report Status
     * @apiSuccess {Array} data Report Data
     * @apiSuccess {String} type Report Type
     *
     * @apiError message Error Message
     *
     */
    public function addAction(
        ReportsService $reportsService,
        ReportGenerator $reportGenerator
    ): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['SUPERVISOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $name = $this->getRequest()->param('name');
        $description = $this->getRequest()->param('description');
        $data = $this->getRequest()->param('data');
        $type = $this->getRequest()->param('type', 'report');
        $mode = $this->getRequest()->param('mode', 1);
        $accounts = json_encode($this->getRequest()->param('accounts', []));

        $em = $this->getDoctrine()->getManager();

        $account = $this->account();
        $folderId = $this->getRequest()->param('folder_id', null);

        $folder = $folderId
            ? $em->getRepository('App:ReportFolder')->findOneBy(['id' => $folderId])
            : $em->getRepository('App:ReportFolder')->findOneBy(['name' => 'account' . $account->getId()]);

        $ancestors = $folder->getRoot();

        if (!$folder || !$ancestors->getName() == 'account' . $account->getId()) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $report = new Reports();

        $report->setName($name);
        $report->setDescription($description);
        $report->setCreatedDate(new \DateTime());
        $report->setUser($this->user());
        $report->setData(json_encode($data));
        $report->setType($type);
        $report->setAccount($this->account());
        $report->setMode($mode);
        $report->setAccounts($accounts);
        $report->setFolder($folder);
        $report->setDateFormat($this->phpDateFormat());

        $em->persist($report);
        $em->flush();

        if (in_array($mode, [ReportMode::SINGLE_MIRROR_PARENT, ReportMode::MULTIPLE_MIRROR_PARENT])) {
            $reportsService->setDateFormat($this->phpDateFormat());
            $reportsService->updateChildReports($report, $this->account(), $this->user());
        }

        $reportGenerator->setDateFormat($this->phpDateFormat());
        $reportGenerator->setUser($this->user());
        $reportGenerator->setTimeZones($this->getTimeZones());
        $reportGenerator->generateReport($report);

        return $this->getResponse()->success([
            'id'           => $report->getId(),
            'name'         => $report->getName(),
            'description'  => $report->getDescription(),
            'user'         => $report->getUser()->getData()->getFullName(),
            'user_id'      => $report->getUser()->getId(),
            'created_date' => $report->getCreatedDate(),
            'status'       => $report->getStatus(),
            'data'         => $report->getData(),
            'type'         => $report->getType(),
            'folder_id'    => $report->getFolder()->getId()
        ]);
    }

    /**
     * @return JsonResponse
     * @api {post} /reports/get Get Report
     * @apiGroup Reports
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} id Report Id
     *
     * @apiSuccess {Integer} id Report Id
     * @apiSuccess {String} name Report Name
     * @apiSuccess {String} description Report Description
     * @apiSuccess {String} user User Full Name
     * @apiSuccess {Integer} user_id User Id
     * @apiSuccess {String} created_date Report Created Date
     * @apiSuccess {String} status Report Status
     * @apiSuccess {Array} data Report Data
     * @apiSuccess {String} type Report Type
     *
     * @apiError message Error Message
     *
     */
    public function getAction(): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $id = (int)$this->getRequest()->param('id');

        $findBy = [
            'id'      => $id,
            'account' => $this->account()
        ];

        if ($this->access() < Users::ACCESS_LEVELS['SUPERVISOR']) {
            $findBy['status'] = 1;
        }

        $report = $this->getDoctrine()->getRepository('App:Reports')->findOneBy($findBy);

        if ($report === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_REPORT_ID);
        }

        $data = json_decode($report->getData(), true);

        $accounts = [];

        if ($report->getAccounts()) {
            $accounts = json_decode($report->getAccounts());
        }

        return $this->getResponse()->success([
            'id'           => $report->getId(),
            'name'         => $report->getName(),
            'description'  => $report->getDescription(),
            'user'         => $report->getUser()->getData()->getFullName(),
            'user_id'      => $report->getUser()->getId(),
            'created_date' => $report->getCreatedDate(),
            'status'       => $report->getStatus(),
            'data'         => $data,
            'type'         => $report->getType(),
            'mode'         => $report->getMode(),
            'accounts'     => $accounts
        ]);
    }

    /**
     * @return JsonResponse
     * @api {post} /reports/edit Edit Report
     * @apiGroup Reports
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} id Report Id
     * @apiParam {String} name Report Name
     * @apiParam {String} description Report Description
     * @apiParam {Array} data Report Data
     *
     * @apiSuccess {Integer} id Report Id
     * @apiSuccess {String} name Report Name
     * @apiSuccess {String} description Report Description
     * @apiSuccess {String} user User Full Name
     * @apiSuccess {Integer} user_id User Id
     * @apiSuccess {String} created_date Report Created Date
     * @apiSuccess {String} status Report Status
     * @apiSuccess {Array} data Report Data
     * @apiSuccess {String} type Report Type
     *
     * @apiError message Error Message
     *
     */
    public function editAction(
        ReportsService $reportsService,
        ReportGenerator $reportGenerator
    ): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['SUPERVISOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $id = (int)$this->getRequest()->param('id');
        $name = $this->getRequest()->param('name');
        $description = $this->getRequest()->param('description');
        $data = $this->getRequest()->param('data');
        $mode = $this->getRequest()->param('mode');
        $report = $this->getDoctrine()->getRepository('App:Reports')->find($id);

        if ($report->getParentId() && $this->account()->getAccountType() == AccountType::CHILD) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS_CHILD_ACCOUNT);
        }

        $oldReportMode = $report->getMode();
        $oldReportAccounts = json_decode($report->getAccounts(), true);

        if ($report === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_REPORT_ID);
        }

        $report->setName($name);
        $report->setDescription($description);
        $report->setData($data);
        $report->setMode($mode);
        $report->setModifiedDate(new \DateTime());
        $report->setDateFormat($this->phpDateFormat());
        $report->setAccounts(json_encode($this->getRequest()->param('accounts', [])));

        $em = $this->getDoctrine()->getManager();

        $em->persist($report);
        $em->flush();

        if ($oldReportMode == ReportMode::SINGLE_MIRROR_PARENT || $oldReportMode == ReportMode::MULTIPLE_MIRROR_PARENT ||
            $report->getMode() == ReportMode::SINGLE_MIRROR_PARENT || $report->getMode() == ReportMode::MULTIPLE_MIRROR_PARENT) {
            $reportsService->setDateFormat($this->phpDateFormat());
            $reportsService->updateChildReports($report, $this->account(), $this->user(), $oldReportAccounts);
        }

        $reportGenerator->setDateFormat($this->phpDateFormat());
        $reportGenerator->setUser($this->user());
        $reportGenerator->setTimeZones($this->getTimeZones());

        $reportGenerator->generateReport($report);

        return $this->getResponse()->success([
            'id'            => $report->getId(),
            'name'          => $report->getName(),
            'description'   => $report->getDescription(),
            'user'          => $report->getUser()->getData()->getFullName(),
            'user_id'       => $report->getUser()->getId(),
            'created_date'  => $report->getCreatedDate(),
            'modified_date' => $report->getModifiedDate(),
            'status'        => $report->getStatus(),
            'data'          => $report->getData(),
            'type'          => $report->getType(),
            'mode'          => $report->getMode(),
            'accounts'      => $report->getAccounts()
        ]);
    }

    /**
     * @return JsonResponse
     * @api {post} /reports/preview Preview Report
     * @apiGroup Reports
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String} name Report Name
     * @apiParam {String} description Report Description
     * @apiParam {Array} data Report Data
     * @apiParam {String} [type] Report Type
     *
     * @apiSuccess {Integer} id Report Id
     * @apiSuccess {String} name Report Name
     * @apiSuccess {String} description Report Description
     * @apiSuccess {String} user User Full Name
     * @apiSuccess {Integer} user_id User Id
     * @apiSuccess {String} created_date Report Created Date
     * @apiSuccess {String} status Report Status
     * @apiSuccess {Array} data Report Data
     * @apiSuccess {String} type Report Type
     * @apiSuccess {Array} results Report Results
     * @apiSuccess {Array} columns Report Columns
     *
     * @apiError message Error Message
     *
     */
    public function previewAction(ReportsService $reportsService): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['SUPERVISOR']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $name = $this->getRequest()->param('name') ? $this->getRequest()->param('name') : 'no name';
        $description = $this->getRequest()->param('description') ? $this->getRequest()->param('description') : 'no description';
        $data = $this->getRequest()->param('data');
        $type = $this->getRequest()->param('type', 'report');
        $mode = $this->getRequest()->param('mode', 1);

        $report = new Reports();

        $report->setName($name);
        $report->setDescription($description);
        $report->setCreatedDate(new \DateTime());
        $report->setUser($this->user());
        $report->setData($data);
        $report->setType($type);
        $report->setAccount($this->account());
        $report->setMode($mode);
        $report->setDateFormat($this->phpDateFormat());

        if ($mode == ReportMode::MULTIPLE || $mode == ReportMode::MULTIPLE_MIRROR_PARENT) {
            if ($this->account()->getAccountType() == AccountType::CHILD) {
                return $this->getResponse()->error(ExceptionMessage::NO_ACCESS);
            }

            $report->setAccounts(json_encode($this->getRequest()->param('accounts', [])));
        }

        $reportsService->setDateFormat($this->phpDateFormat());
        $reportsService->setUser($this->user());
        $reportsService->setTimeZones($this->getTimeZones());
        $result = $reportsService->getPreview($report);

        return $this->getResponse()->success([
            'id'            => $report->getId(),
            'name'          => $report->getName(),
            'description'   => $report->getDescription(),
            'user'          => $report->getUser()->getData()->getFullName(),
            'user_id'       => $report->getUser()->getId(),
            'created_date'  => $report->getCreatedDate(),
            'modified_date' => $report->getModifiedDate(),
            'status'        => $report->getStatus(),
            'data'          => $report->getData(),
            'type'          => $report->getType(),
            'results'       => $result['results'],
            'columns'       => $result['columns'],
            'count'         => $result['count'],
            'isPreview'     => true
        ]);
    }

    /**
     * @return JsonResponse
     * @api {post} /reports/result View Report
     * @apiGroup Reports
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {Integer} id Report Id
     *
     * @apiSuccess {Array} data Report Data
     * @apiSuccess {Array} results Report Results
     * @apiSuccess {Array} columns Report Columns
     * @apiSuccess {String} name Report Name
     * @apiSuccess {String} description Report Description
     *
     * @apiError message Error Message
     *
     */
    public function resultAction(
        ReportsService $reportsService,
        ReportGenerator $reportGenerator
    ): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $id = (int)$this->getRequest()->param('id');

        $findBy = [
            'id'      => $id,
            'account' => $this->account()
        ];

        if ($this->access() < Users::ACCESS_LEVELS['SUPERVISOR']) {
            $findBy['status'] = 1;
        }

        $report = $this->getDoctrine()->getRepository('App:Reports')->findOneBy($findBy);

        if ($report === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_REPORT_ID);
        }

        $offset = $this->getRequest()->param('offset');
        $limit = $this->getRequest()->param('limit');
        $sortBy = $this->getRequest()->param('sortBy');
        $sortDir = $this->getRequest()->param('sortDir');
        $generateReport = $this->getRequest()->param('generateReport');

        $reportsService->setDateFormat($this->phpDateFormat());
        $reportsService->setUser($this->user());
        $reportsService->setTimeZones($this->getTimeZones());

        if ($generateReport) {
            $reportGenerator->setDateFormat($this->phpDateFormat());
            $reportGenerator->setUser($this->user());
            $reportGenerator->setTimeZones($this->getTimeZones());
            $result = $reportGenerator->generateReport($report);
        } else {
            $reportReady = $reportsService->isValidReport($report);

            if ($reportReady) {
                $result = $reportsService->getResultsFromCache($report, $offset, $limit, $sortBy, $sortDir);
            } else {
                $result = $reportsService->getPreview($report);
            }
        }

//        $result = $reportGenerator->generateReport($report);

        return $this->getResponse()->success([
            'id'          => $report->getId(),
            'data'        => $report->getData(),
            'results'     => $result['results'],
            'columns'     => $result['columns'],
            'count'       => $result['count'],
            'isPreview'   => $result['isPreview'],
            //            'stack'       => $stack ?? '',
            'name'        => $report->getName(),
            'description' => $report->getDescription(),
            'parent_id'   => $report->getParentId() ? $report->getParentId()->getId() : null
        ]);
    }

    /**
     * Export report, big reports are exported in chunks
     *
     * @return JsonResponse
     */
    public function exportAction(ReportsService $reportsService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$session = $this->getDoctrine()->getRepository('App:UsersSessions')->findOneBy(['token' => $this->getToken()])) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND, 404);
        }

        $type = $this->getRequest()->param('type');

        if (!in_array($type, ['xlsx', 'csv'])) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_FILE_TYPE, 404);
        }

        $id = (int)$this->getRequest()->param('report_id');

        $report = $this->getDoctrine()->getRepository('App:Reports')->find($id);
        $chunk = (int)$this->getRequest()->param('chunk');
        $rowsInChunk = 200000;

        $user = $session->getUser();
        $title = $report->getName() . ', ' . $this->convertDateTime($user);

        $reportsService->setDateFormat($this->phpDateFormat());
        $reportsService->setUser($this->user());
        $reportsService->setTimeZones($this->getTimeZones());

        if ($type == 'csv') {
            $dataToExport = $this->getRequest()->param('csv_export');
            return $this->getResponse()->success($reportsService->generateCsv($report, $dataToExport, $title, $rowsInChunk, $chunk));
        }

        if ($type == 'xlsx') {
            $dataToExport = $this->getRequest()->param('xls_export');
            return $this->getResponse()->success($reportsService->generateXlsx($report, $dataToExport, $title));
        }
    }

    /**
     * @param Request $request
     * @param $type
     * @param Reports $report
     * @return BinaryFileResponse|JsonResponse
     */
    public function downloadAction(S3ClientFactory $s3ClientFactory, Request $request, $type, $reportId)
    {
        if (!$request->isMethod('GET')) {
            $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 401);
        }

        $token = $request->query->get('token');

        if (!$session = $this->getDoctrine()->getRepository('App:UsersSessions')->findOneBy(['token' => $token])) {
            return $this->getResponse()->error(ExceptionMessage::UNAUTHORIZED, 401);
        }

        $report = $this->getDoctrine()->getRepository('App:Reports')->find($reportId);

        if (!$report) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND, 404);
        }

        $account = $this->account($session->getUser());

        if ($account !== $report->getAccount()) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $filename = md5('final_report' . $report->getId() . $type);
        $downloadName = 'report.' . $type;

        $client = $s3ClientFactory->getClient();
        $bucket = $this->getParameter('aws_bucket_name');

        try {
            $result = $client->getObject([
                'Bucket' => $bucket,
                'Key'    => 'reports_exports/' . $filename,
            ]);

            return new Response(
                $result['Body'],
                200,
                [
                    'Content-Type'        => $result['ContentType'],
                    'Content-Disposition' => sprintf('attachment; filename="%s"', $downloadName),
                ]
            );
        } catch (S3Exception $e) {
            throw new NotFoundHttpException(404);
        }
    }

    public function createFolderAction(ReportsFoldersService $reportsFoldersService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $name = $this->getRequest()->param('name');
        $parentFolderId = $this->getRequest()->param('parentId', null);
        $account = $this->account();

        $reportsFoldersService->createFolder($name, $account, $parentFolderId);

        return $this->getResponse()->success(['message' => 'Folder created']);
    }

    public function deleteFolderAction(ReportsFoldersService $reportsFoldersService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $account = $this->account();
        $folderId = $this->getRequest()->param('folder_id', null);
        $deleteContent = (bool)$this->getRequest()->param('delete_content', false);

        try {
            $backToFolderId = $reportsFoldersService->deleteFolder($account, $folderId, $deleteContent);
        } catch (\Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success([
            'message'           => 'Folder removed',
            'back_to_folder_id' => $backToFolderId
        ]);
    }

    public function accountFoldersAction(ReportsFoldersService $reportsFoldersService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $account = $this->account();
        $folders = $reportsFoldersService->getFolders($account);

        return $this->getResponse()->success(['folders' => $folders]);
    }

    public function moveToFolderAction(ReportsFoldersService $reportsFoldersService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $account = $this->account();

        $itemType = $this->getRequest()->param('item_type');
        $itemId = $this->getRequest()->param('item_id', null);
        $folderId = $this->getRequest()->param('folder_id', null);

        try {
            switch ($itemType) {
                case 'report':
                {
                    $reportsFoldersService->moveReportToFolder($account, $itemId, $folderId);
                    break;
                }

                case'folder':
                {
                    $reportsFoldersService->moveFolderToFolder($account, $itemId, $folderId);
                }
            }
        } catch (\Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success(['message' => 'Moved!']);
    }

    public function editFolderAction(ReportsFoldersService $reportsFoldersService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $account = $this->account();
        $folderId = $this->getRequest()->param('id');
        $newName = $this->getRequest()->param('name');

        try {
            $reportsFoldersService->renameFolder($account, $folderId, $newName);
        } catch (\Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success(['message' => 'Renamed!']);
    }

    public function saveTopReportsAction(ReportsService $reportsService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $user = $this->user();
        $reportsIds = $this->getRequest()->param('ids', []);
        $account = $this->account();

        try {
            $reportsService->saveTopReports($user, $account, $reportsIds);
        } catch (\Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success([
            'top_reports_ids' => $reportsIds,
            'message'         => 'Top 5 reports saved!'
        ]);
    }

    public function indexTopReportsAction()
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $user = $this->user();
        $settings = $this->getDoctrine()->getRepository('App:UsersSettings')->findOneBy(
            [
                'user' => $user,
                'name' => 'top_reports_account_' . $this->account()->getId()
            ]
        );

        $topReportsIds = [];

        if ($settings) {
            $topReportsIds = json_decode($settings->getValue(), true);
        }

        $reports = $this->getDoctrine()->getRepository('App:Reports')->findBy([
            'account' => $this->account(),
            'id'      => $topReportsIds
        ], ['name' => 'ASC']);

        $reports = $this->formatReportsList($reports);

        return $this->getResponse()->success([
            'reports'         => $reports,
            'top_reports_ids' => $topReportsIds,
        ]);
    }

    public function indexFormsAction($mode)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $account = $this->account();

        if (in_array($mode, [ReportMode::SINGLE, ReportMode::SINGLE_MIRROR_PARENT])) {
            if ($account->getAccountType() == AccountType::CHILD) {
                $childAccountsForms = $this->getDoctrine()->getRepository('App:Forms')->findAllForAccountAndAccessLevel($account, $this->access());
                $parentAccountsForms = $this->getDoctrine()->getRepository('App:Forms')->findAllForAccountAndAccessLevel($account->getParentAccount(), $this->access());

                $childAccountsFormsIds = array_map(function ($item) {
                    return $item->getId();
                }, $childAccountsForms);
                $repository = [];

                foreach ($parentAccountsForms as $parentAccountsForm) {
                    if (in_array($parentAccountsForm->getId(), $childAccountsFormsIds)) {
                        $repository[] = $parentAccountsForm;
                        continue;
                    }

                    $data = $this->getDoctrine()->getRepository('App:FormsData')->findBy(['form' => $parentAccountsForm, 'account_id' => $account]);
                    if ($data) {
                        $repository[] = $parentAccountsForm;
                    }
                }
                return $this->getResponse()->success(['forms' => $this->formatFormsResults($repository)]);
            }

            $repository = $this->getDoctrine()->getRepository('App:Forms')->findAllForAccountAndAccessLevel($account, $this->access());
            return $this->getResponse()->success(['forms' => $this->formatFormsResults($repository)]);
        }

        if (in_array($mode, [ReportMode::MULTIPLE, ReportMode::MULTIPLE_MIRROR_PARENT])) {
            if ($account->getAccountType() == AccountType::PARENT) {
                $repository = $this->getDoctrine()->getRepository('App:Forms')->findAllForAccountAndAccessLevel($account, $this->access());
            }

            if ($account->getAccountType() == AccountType::DEFAULT) {
                $modules = $this->getDoctrine()->getRepository('App:Modules')->findBy(['group' => 'organization']);
                $modulesIds = [];
                foreach ($modules as $module) {
                    $modulesIds[] = $module->getId();
                }
                $repository = $this->getDoctrine()->getRepository('App:Forms')->findAllByAccountAccessModules($account, $this->access(), $modulesIds);
            }

            if (!isset($repository)) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_CHILD_ACCOUNT);
            }

            return $this->getResponse()->success(['forms' => $this->formatFormsResults($repository)]);
        }
    }

    /**
     * @param $repository
     * @return array
     */
    private function formatFormsResults($repository): array
    {
        $data = [];
        foreach ($repository as $item) {
            $last_modification_date = $item->getLastActionDate();
            $data[$item->getId()] = [
                'id'                     => $item->getId(),
                'name'                   => $item->getName(),
                'description'            => $item->getDescription(),
                'data'                   => $item->getData(),
                'type'                   => $item->getType(),
                'user'                   => $item->getUser()->getData()->getFullName(),
                'created_date'           => $item->getCreatedDate(),
                'last_action_user'       => $item->getLastActionUser() === null ? '' : $item->getLastActionUser()->getData()->getFullName(),
                'last_modification_date' => $last_modification_date === null ? '' : $last_modification_date->format('Y-m-d H:i:s'),
                'status'                 => $item->getStatus(),
                'publish'                => $item->getPublish(),
                'conditionals'           => $item->getConditionals(),
                'calculations'           => $item->getCalculations(),
                'hide_values'            => $item->getHideValues(),
                'extra_validation_rules' => $item->getExtraValidationRules() ?: "[]",
                'system_conditionals'    => $item->getSystemConditionals(),
                'columns_map'            => $item->getColumnsMap(),
                'module_key'             => $item->getModule()->getKey(),
                'is_core'                => $item->getModule() ? ($item->getModule()->getGroup() == 'core') : false
            ];
        }
        return $data;
    }


    protected function formatReportsList($reports): array
    {
        $reportsList = [];

        foreach ($reports as $report) {
            $reportsList[] = [
                'id'            => $report->getId(),
                'name'          => $report->getName(),
                'description'   => $report->getDescription(),
                'user'          => $report->getUser()->getData()->getFullName(),
                'user_id'       => $report->getUser()->getId(),
                'created_date'  => $report->getCreatedDate(),
                'modified_date' => $report->getModifiedDate(),
                'status'        => $report->getStatus(),
                'data'          => $report->getData(),
                'type'          => $report->getType(),
                'mode'          => $report->getMode(),
                'count'         => $report->getResultsCount(),
                'folder'        => $report->getFolder()->getId(),
                'parent_id'     => $report->getParentId() ? $report->getParentId()->getId() : null
            ];
        }
        return $reportsList;
    }
}
