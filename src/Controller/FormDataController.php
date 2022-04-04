<?php

namespace App\Controller;

use App\Domain\Form\FormConditionsRender;
use App\Domain\Form\FormSchemaHelper;
use App\Domain\Form\FormToArrayTransformer;
use App\Domain\Form\SharedFieldsService;
use App\Domain\Form\SharedFormPreviewsHelper;
use App\Domain\FormValues\FormValuesParser;
use App\Entity\Forms;
use App\Entity\Users;
use App\Event\FormCreatedEvent;
use App\Event\FormDataRemovedEvent;
use App\Exception\ExceptionMessage;
use App\Utils\Helper;
use App\Service\FormCrudWidget;
use App\Service\FormDataService;
use App\Service\Forms\FormDataValuesService;
use App\Service\S3ClientFactory;
use Aws\S3\Exception\S3Exception;
use Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function Sentry\captureException;

/**
 * Class FormDataController
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
class FormDataController extends Controller
{
    public function getByIdAction(
        FormDataValuesService $valuesService,
        SharedFieldsService $sharedFieldsService,
        FormDataService $formDataService,
        SharedFormPreviewsHelper $formPreviewsService
    ): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $dataId = $this->getRequest()->param('data_id');

        if ($dataId === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_DATA_ID, 422);
        }

        $data = $this->getDoctrine()->getRepository('App:FormsData')->find($dataId);

        if ($data === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_DATA, 422);
        }

        $form = $data->getForm();

        if (in_array($form->getModule()->getRole(), ['activities_services', 'assessment_outcomes', 'organization_general', 'organization_organization']) && $form->getAccessLevel() > $this->access()) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 403);
        }

        $formArr = FormToArrayTransformer::getFormAsArr($form);

        $values = $valuesService->getRawValues($data);

        $assignmentId = $data->getAssignment() ? $data->getAssignment()->getId() : null;

        $sharedFieldsValues = $sharedFieldsService->getSharedFieldsValues($form, $this->account(), $data->getElementId(), $assignmentId);

        $values = array_merge($values, $sharedFieldsValues);

        $formDataArr = $formDataService->setFormData($data)->getFormDataAsArr();

        $sharedForm = $this->getDoctrine()->getRepository('App:SharedForm')->findOneBy(['formData' => $data]);
        $formPreviews = $formPreviewsService->getFormPreviews($form, $this->account(), $data->getElementId(), $assignmentId);

        return $this->getResponse()->success([
            'form'               => $formArr,
            'values'             => $values,
            'form_data'          => $formDataArr,
            'shared_form_status' => $sharedForm ? $sharedForm->getStatus() : null,
            'form_previews'      => $formPreviews
        ]);
    }


    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @api {post} /form/data/delete Delete Form Data
     * @apiGroup Forms
     *
     * @apiParam {Integer} data_id FromData Id
     *
     * @apiError message Error Message
     *
     */
    public function deleteAction(EventDispatcherInterface $eventDispatcher)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->access() < Users::ACCESS_LEVELS['CASE_MANAGER']) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $data_id = $this->getRequest()->param('data_id');

        if ($data_id === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_DATA_ID);
        }

        /** @var \App\Entity\FormsData $form */
        $data = $this->getDoctrine()->getRepository('App:FormsData')->find($data_id);

        if ($data->getAssignment() && ($this->access() < Users::ACCESS_LEVELS['SUPERVISOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 403);
        }

        if ($data === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_DATA);
        }

        $form = $data->getForm();
        $account = $data->getAccount();
        $participantUserId = $data->getElementId();
        $assignment = $data->getAssignment();

        $em = $this->getDoctrine()->getManager();

        foreach ($data->getValues() as $value) {
            $em->remove($value);
        }

        $em->remove($data);
        $em->flush();

        $eventDispatcher->dispatch(
            new FormDataRemovedEvent($form, $account, $participantUserId, $assignment),
            FormDataRemovedEvent::class
        );

        return $this->getResponse()->success();
    }

    /**
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @api {post} /form/data/duplicate Duplicate Form Data
     * @apiGroup Forms
     *
     * @apiParam {Integer} data_id FromData Id
     *
     * @apiError message Error Message
     *
     */
    public function duplicateAction(EventDispatcherInterface $eventDispatcher)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $dataId = $this->getRequest()->param('data_id');

        if ($dataId === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_DATA_ID);
        }

        $data = $this->getDoctrine()->getRepository('App:FormsData')->find($dataId);

        if ($data === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_DATA);
        }

        $values = $data->getValues();
        $data = clone $data;

        $data->setId(null);
        $data->setCreatedDate(new \DateTime());
        $data->setUpdatedDate(new \DateTime());
        $data->setCreator($this->user());
        $data->setEditor($this->user());

        $em = $this->getDoctrine()->getManager();

        $em->persist($data);
        $em->flush();

        foreach ($values as $value) {
            $value = clone $value;

            $value->setId(null);
            $value->setData($data);

            $em->persist($value);
        }

        $em->flush();

        $em->refresh($data);

        $eventDispatcher->dispatch(new FormCreatedEvent($data), FormCreatedEvent::class);

        return $this->getResponse()->success();
    }



    /**
     * @param Request $request
     * @param string $fileName
     *
     * @return Response
     */
    public function downloadFileAction(
        Request $request,
        S3ClientFactory $s3ClientFactory,
        string $fileName
    ): ?Response
    {
        $token = $request->query->get('token');
        $forceDownload = $request->query->get('force') === 'true';
        $downloadName = $request->query->get('download-name') ?? null;

        if (!$session = $this->getDoctrine()->getRepository('App:UsersSessions')->findOneBy(['token' => $token])) {
            return $this->getResponse()->error(ExceptionMessage::UNAUTHORIZED, 401);
        }

        $user = $session->getUser();
        $userAccounts = $user->getAccounts();

        $parts = explode('_', $fileName);
        $optname = $parts[0];
        $fileName = $parts[1];

        if (!$forceDownload) {
            $formValue = $this->getDoctrine()->getRepository('App:FormsValues')->findFileByOptNameAndFilename('file-'.$optname, $fileName);

            if ( ! $formValue) {
                return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_FILE, 404);
            }

            $fileAccount = $formValue->getData()->getAccount();

            if ( ! $userAccounts->contains($fileAccount)) {
                return $this->getResponse()->error(ExceptionMessage::UNAUTHORIZED, 401);
            }

            $formValueFiles = json_decode($formValue->getValue(), true);

            foreach ($formValueFiles as $item) {
                if ($item['file'] == $fileName) {
                    $downloadName = $item['name'];
                    break;
                }
            }
        }

        $client = $s3ClientFactory->getClient();
        $bucket = $this->getParameter('aws_bucket_name');
        $prefix = $this->getParameter('aws_forms_folder');

        try {
            $result = $client->getObject([
                'Bucket' => $bucket,
                'Key'    => $prefix . '/' . $fileName,
            ]);

            return new Response(
                $result['Body'],
                200,
                [
                    'Content-Type'        => $result['ContentType'],
                    'Content-Disposition' => sprintf('attachment; filename="%s"', $downloadName ?? $fileName),
                ]
            );
        } catch (S3Exception $e) {
            throw new NotFoundHttpException(404);
        }
    }

    /**
     * Get forms for FormCRUD view (form widgets)
     */
    public function groupedAction(FormCrudWidget $formCrudWidget): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $moduleKey = $this->getRequest()->param('module');
        $elementId = $this->getRequest()->param('element_id', null);
        $assignmentId = $this->getRequest()->param('assignment_id', null);
        $account = $this->account();
        $accessLevel = $this->access($this->user());

        try {
            $formCrudWidget->setup($account, $moduleKey, $elementId, $assignmentId, $accessLevel);
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        try {
            $index = $formCrudWidget->getIndex();
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success($index);
    }

    public function printAction(
        FormConditionsRender $formConditionsRender,
        FormDataService $formDataService
    ): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $dataId = $this->getRequest()->param('data_id');

        if ($dataId === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_DATA_ID, 422);
        }

        $data = $this->getDoctrine()->getRepository('App:FormsData')->find($dataId);

        if ($data === null) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_DATA, 422);
        }

        $form = $data->getForm();

        if (in_array($form->getModule()->getRole(), ['activities_services', 'assessment_outcomes', 'organization_general', 'organization_organization']) && $form->getAccessLevel() > $this->access()) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 403);
        }

        $formArr = FormToArrayTransformer::getFormAsArr($form);
        $values = $formConditionsRender->setFormData($data)->renderData(true);
        $formDataArr = $formDataService->setFormData($data)->getFormDataAsArr();

        $sharedForm = $this->getDoctrine()->getRepository('App:SharedForm')->findOneBy(['formData' => $data]);

        return $this->getResponse()->success([
            'form'               => $formArr,
            'values'             => $values,
            'form_data'          => $formDataArr,
            'shared_form_status' => $sharedForm ? $sharedForm->getStatus() : null
        ]);
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\JsonResponse|Response
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function exportHistoryAction(
        Request $request,
        FormSchemaHelper $formHelper,
        FormValuesParser $formValuesParser
    )
    {
        if ($request->isMethod('GET')) {
            $token = $request->query->get('token');

            if (!$session = $this->getDoctrine()->getRepository('App:UsersSessions')->findOneBy(['token' => $token])) {
                return $this->getResponse()->error(ExceptionMessage::NOT_FOUND, 401);
            }

            $pid = $request->query->get('pid');
            $aid = $request->query->get('aid', false);
            $type = in_array(
                $request->query->get('type'),
                ['csv', 'xls', 'pdf']
            ) ? $request->query->get('type') : 'csv';

            if (!$participant = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                'id' => $pid
            ])) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_PARTICIPANT, 404);
            }

            if (!$this->can(
                Users::ACCESS_LEVELS['VOLUNTEER'],
                $session->getUser(),
                $participant->getAccounts()->first()
            )) {
                return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
            }

            if ($aid !== false) {
                if (!$assignment = $this->getDoctrine()->getRepository('App:Assignments')->findOneBy([
                    'id'          => $aid,
                    'participant' => $pid
                ])) {
                    return $this->getResponse()->error(ExceptionMessage::INVALID_ASSIGNMENT, 404);
                }
            } else {
                $assignment = null;
            }

            $data = [];
            $values = [];
            $names = [];

            $columns = [];

            $completedForms = $this->getDoctrine()->getRepository('App:FormsData')->findBy([
                'assignment' => $assignment ? $assignment->getId() : null,
                'element_id' => $participant->getId()
            ]);

            foreach ($completedForms as $completedForm) {
                $names[$completedForm->getId()] = $completedForm->getForm()->getName();
                $form = $completedForm->getForm();

                if ($completedForm->getCreatedDate() < $completedForm->getForm()->getLastActionDate()) {
                    foreach ($form->getFormsHistory() as $historicalForm) {
                        if ($completedForm->getCreatedDate() < $historicalForm->getDate()) {
                            continue;
                        }
                        $form = new Forms();
                        $form->setData($historicalForm->getData());
                        break;
                    }
                }

                $formHelper->setForm($form);

                $formValuesParser->setCompletedFormData($completedForm);
                $fieldsInForm = $formHelper->getFlattenColumns();

                foreach ($fieldsInForm as $fieldInForm) {
                    if (in_array($fieldInForm['type'], ['header', 'divider', 'row', 'text-entry'])) {
                        continue;
                    }

                    $values[$completedForm->getId()][$fieldInForm['name']] = $formValuesParser->getFieldValue($fieldInForm, ', ');
                    $columns[$completedForm->getId()][$fieldInForm['name']] = [
                        'description' => $fieldInForm['description'],
                        'type'        => $fieldInForm['type'],
                        'values'      => isset($fieldInForm['values']) ? $fieldInForm['values'] : []
                    ];
                }

                if (in_array($completedForm->getModule()->getId(), [4, 5])) {
                    $columns[$completedForm->getId()]['completed_by'] = [
                        'description' => 'Completed By',
                        'type'        => ''
                    ];
                    $columns[$completedForm->getId()]['modified_by'] = [
                        'description' => 'Modified By',
                        'type'        => ''
                    ];
                    $columns[$completedForm->getId()]['case_manager'] = [
                        'description' => 'Case Manager',
                        'type'        => ''
                    ];

                    $completedBy = [
                        'user' => $completedForm->getCreator() ? $completedForm->getCreator()->getData()->getFullName() : '',
                        'date' => $completedForm->getCreatedDate() ? $this->convertDateTime(
                            $session->getUser(),
                            $completedForm->getCreatedDate()
                        ) : ''
                    ];

                    $modifiedBy = [
                        'user' => $completedForm->getEditor() ? $completedForm->getEditor()->getData()->getFullName() : '',
                        'date' => $completedForm->getUpdatedDate() ? $this->convertDateTime(
                            $session->getUser(),
                            $completedForm->getUpdatedDate()
                        ) : ''
                    ];

                    $caseManager = $completedForm->getManager() ? $completedForm->getManager()->getData()->getFullName() : '';

                    $values[$completedForm->getId()]['completed_by'] = implode(' at ', $completedBy);
                    $values[$completedForm->getId()]['modified_by'] = implode(' at ', $modifiedBy);
                    $values[$completedForm->getId()]['case_manager'] = $caseManager;
                }
            }

            $labelsCount = [];

            foreach ($values as $completedFormsDataId => $formFields) {
                foreach ($formFields as $fieldName => $fieldValue) {

                    // check if signature pad has image and if yes - set value as "Signed"

                    if ((strpos($fieldName, 'signature-') !== false) && !empty($fieldValue)) {
                        $fieldValue = 'Signed';
                    }

                    // prevent overwriting in export if fields descriptions are the same

                    if (isset($columns[$completedFormsDataId][$fieldName])) {
                        $label = $columns[$completedFormsDataId][$fieldName]['description'];

                        if (isset($data[$completedFormsDataId]) && array_key_exists($label, $data[$completedFormsDataId])) {
                            isset($labelsCount[$label]) ? $labelsCount[$label]++ : $labelsCount[$label] = 1;
                            $data[$completedFormsDataId][$columns[$completedFormsDataId][$fieldName]['description'] . ' (' . $labelsCount[$label] . ')'] = $fieldValue;
                            continue;
                        }

                        $data[$completedFormsDataId][$columns[$completedFormsDataId][$fieldName]['description']] = $fieldValue;
                    }
                }
            }


            switch ($type) {
                case 'xls':
                    return $this->exportXls(['data' => $data, 'names' => $names, 'user' => $session->getUser()]);
                    break;
                case 'pdf':
                    return $this->exportPdf(['data' => $data, 'names' => $names, 'user' => $session->getUser()]);
                    break;
                default:
                    return $this->exportCsv(['data' => $data, 'names' => $names, 'user' => $session->getUser()]);
                    break;
            }
        }

        return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD, 401);
    }

    /**
     * @param $data
     * @return Response
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    private function exportXls($data)
    {
        $output[] = [
            'Date of Export ' . $this->convertDateTime($data['user'])
        ];

        foreach ($data['data'] as $id => $form) {
            $output[] = [];
            $output[] = ['File: ' . $data['names'][$id]];
            $output[] = [];
            $output[] = array_keys($form);
            $output[] = array_values($form);
        }

        $spreadsheet = new Spreadsheet();
        $writer = new Xlsx($spreadsheet);
        $sheet = $spreadsheet->getActiveSheet();
        $file_name = 'Files';

        foreach ($output as $kR => $vR) {
            foreach ($vR as $kC => $vC) {
                $sheet->setCellValueByColumnAndRow($kC + 1, $kR + 1, $vC);
            }
        }

        ob_start();

        $writer->save('php://output');

        return new Response(
            ob_get_clean(),
            200,
            [
                'Content-Type'        => 'application/vnd.ms-excel; charset=utf-8',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $file_name . '.xlsx')
            ]
        );
    }

    /**
     * @param $data
     * @return Response
     */
    private function exportPdf($data)
    {
        $html = $this->renderView('Files/pdf.html.twig', [
            'data' => $data,
            'date' => $this->convertDateTime($data['user'])
        ]);

        $file_name = 'Files';

        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $file_name . '.pdf')
            ]
        );
    }

    /**
     * @param $data
     * @return Response
     */
    private function exportCsv($data)
    {
        $output[] = [
            'Date of Export ' . $this->convertDateTime($data['user'])
        ];

        foreach ($data['data'] as $id => $form) {
            $output[] = [];
            $output[] = ['File: ' . $data['names'][$id]];
            $output[] = [];
            $output[] = array_keys($form);
            $rows = array_values($form);

            foreach ($rows as $k => $v) {
                $rows[$k] = '"' . $v . '"';
            }

            $output[] = $rows;
        }

        $file_name = 'Files';

        return new Response(
            (Helper::csvConvert($output)),
            200,
            [
                'Content-Type'        => 'application/csv',
                'Content-Disposition' => sprintf('attachment; filename="%s"', $file_name . '.csv'),
            ]
        );
    }
}
