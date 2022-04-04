<?php

namespace App\Controller;

use App\Domain\DataImport\ImportToArrayTransformer;
use App\Domain\DataImport\ImportCreator;
use App\Domain\DataImport\ImportHistoryManager;
use App\Domain\DataImport\ImportManager;
use App\Domain\DataImport\ImportWorker;
use App\Entity\Users;
use App\Exception\ExceptionMessage;
use App\Utils\Helper;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function Sentry\captureException;

class ImportsController extends Controller
{
    public function formsAction(): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $forms = $this->getDoctrine()->getRepository('App:Forms')->findByAccount($this->account());

        $data = [];

        foreach ($forms as $form) {
            if (in_array($form->getModule()->getKey(), ['organization_general', 'organization_organization'])) {
                continue;
            }

            $data[] = [
                'id'           => $form->getId(),
                'name'         => $form->getName(),
                'description'  => $form->getDescription(),
                'createdBy'    => [
                    'user' => $form->getUser() ? $form->getUser()->getData()->getFullName() : null,
                    'date' => $form->getCreatedDate()
                ],
                'lastModified' => [
                    'user' => $form->getLastActionUser() ? $form->getLastActionUser()->getData()->getFullName() : null,
                    'date' => $form->getLastActionDate()
                ]
            ];
        }

        $caseNote = [
            'id'           => 0,
            'name'         => 'Communication Notes',
            'description'  => 'Communication Notes',
            'createdBy'    => [
                'user' => 'System',
                'date' => new \DateTime()
            ],
            'lastModified' => [
                'user' => 'System',
                'date' => new \DateTime()
            ]
        ];

        return $this->getResponse()->success([
            'forms' => array_merge($data, [$caseNote])
        ]);
    }

    public function templateAction(Request $request, ImportManager $importManager): Response
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 403);
        }

        if (!$request->isMethod('GET')) {
            return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 500);
        }

        $formId = (int)$request->query->get('id');

        $importManager->setup($formId);

        $fileContent = $importManager->getTemplate();

        if ($formId === 0) {
            $file = 'Template_' . $formId;
        } else {
            $file = 'Template_CaseNotes';
        }

        return new Response(
            $fileContent,
            200,
            [
                'Content-Type'        => 'application/csv',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $file . '.csv'),
            ]
        );
    }

    public function uploadAction(ImportManager $importManager): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $data = json_decode($this->getRequest()->post('data'), true);
        $formId = $data['form_id'];

        $files = $this->getRequest()->files();

        $file = reset($files);

        $importManager->setup($formId);

        try {
            $importSettings = $importManager->uploadFile($file);
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success($importSettings);
    }

    public function preValidateAction(ImportManager $importManager): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 403);
        }

        $filename = $this->getRequest()->param('filename');
        $formId = $this->getRequest()->param('form_id', 0);
        $map = $this->getRequest()->param('map');
        $keyField = $this->getRequest()->param('key_field');
        $ignoreRequired = $this->getRequest()->param('ignore_required', false);

        $importManager->setup($formId);

        try {
            $summary = $importManager->preValidate($filename, $map, $keyField, $ignoreRequired);
        } catch (Exception $e) {
            return $this->getResponse()->error($e->getLine() . '-' . $e->getFile() . '-' . $e->getMessage());
        }

        return $this->getResponse()->success([
            'summary' => $summary
        ]);
    }

    public function createImportAction(ImportCreator $importCreator): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 403);
        }

        $filename = $this->getRequest()->param('filename');
        $formId = $this->getRequest()->param('form_id', 0);
        $map = $this->getRequest()->param('map');
        $originalFilename = $this->getRequest()->param('original_filename', '');
        $keyField = $this->getRequest()->param('key_field', []);

        $totalRows = $this->getRequest()->param('total_rows', 0);
        $exceptionsRows = $this->getRequest()->param('exceptions_rows', []);

        try {
            $import = $importCreator->create($this->account(), $this->user(), $formId, $filename, $originalFilename, $map, $totalRows, $exceptionsRows, $keyField);
        } catch (Exception $e) {
            return $this->getResponse()->error($e->getLine() . '-' . $e->getFile() . '-' . $e->getMessage());
        }

        return $this->getResponse()->success([
            'import'  => ImportToArrayTransformer::importToArray($import),
            'message' => 'Import created!'
        ]);
    }

    public function runImportAction(ImportWorker $importWorker): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 403);
        }

        $import = $this->getDoctrine()->getRepository('App:Imports')->find($this->getRequest()->param('id', null));

        if (!$import) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_IMPORT);
        }

        $itemsPerIteration = 50;
        $iterations = ceil($import->getTotalRows() / $itemsPerIteration);

        for ($i = 0; $i < $iterations; $i++) {
            $importWorker->batchLater()->runImport($import->getId(), $i * $itemsPerIteration, $itemsPerIteration - 1);
        }

        return $this->getResponse()->success(['message' => 'Import added to queue.']);
    }

    public function historyAction(ImportHistoryManager $importHistoryManager): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 403);
        }

        $importHistoryManager->setAccount($this->account());
        $importHistoryManager->deleteExpired();

        $data = $importHistoryManager->getHistoryIndex();

        return $this->getResponse()->success([
            'imports' => $data
        ]);
    }

    public function showAction(ImportHistoryManager $importHistoryManager): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $id = $this->getRequest()->param('id');

        if (!$import = $this->getDoctrine()->getRepository('App:Imports')->find($id)) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_IMPORT, 422);
        }

        $data = $importHistoryManager->show($import);

        return $this->getResponse()->success($data);
    }

    public function exportExceptionsAction(Request $request, ImportHistoryManager $importHistoryManager)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['PROGRAM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $id = $request->get('id');

        if (!$import = $this->getDoctrine()->getRepository('App:Imports')->find($id)) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_IMPORT, 422);
        }

        $data = $importHistoryManager->exportExceptions($import);

        return new Response(
            (Helper::csvConvert($data)),
            200,
            [
                'Content-Type'        => 'application/csv',
                'Content-Disposition' => sprintf('attachment; filename="%s"', 'ImportExceptions.csv'),
            ]
        );
    }
}
