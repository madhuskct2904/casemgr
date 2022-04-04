<?php


namespace App\Domain\Form;


use App\Domain\FormData\FormDataTableHelper;
use App\Entity\Accounts;
use App\Entity\Forms;
use App\Domain\Form\FormSchemaHelper;
use Doctrine\ORM\EntityManagerInterface;

/** Generate data for "shared form preview" element in form builder */

final class SharedFormPreviewsHelper
{
    private EntityManagerInterface $em;
    private \App\Domain\Form\FormSchemaHelper $formHelper;
    private FormDataTableHelper $completedFormsTableService;

    public function __construct(EntityManagerInterface $em, FormSchemaHelper $formHelper, FormDataTableHelper $completedFormsTableService)
    {
        $this->em = $em;
        $this->formHelper = $formHelper;
        $this->completedFormsTableService = $completedFormsTableService;
    }

    public function getFormPreviews(Forms $form, Accounts $account, ?int $participantUserId = null, ?int $assignmentId = null): array
    {
        $columns = $this->formHelper->getFlattenColumnsForForm($form);

        $formPreviews = [];

        foreach ($columns as $col) {

            if ($col['type'] === 'shared-form-preview') {
                $formForPreview = $this->em->getRepository('App:Forms')->find($col['formId']);
                $this->completedFormsTableService->setForm($formForPreview);

                $formDataRepository = $this->em->getRepository('App:FormsData');

                $formsData = null;

                if ($col['formData'] === 'daterange-field' && isset($col['formDataRange'][0], $col['formDataRange'][1]) && $col['dateRangeField']) {

                    $dateFrom = \DateTime::createFromFormat('m/d/Y', $col['formDataRange'][0]);
                    $dateTo = \DateTime::createFromFormat('m/d/Y', $col['formDataRange'][1]);

                    if ($dateFrom && $dateTo) {
                        $formsDataIds = $formDataRepository->findForParticipantAssignmentAccountFieldDateRange(
                            $participantUserId,
                            $assignmentId,
                            $account,
                            $formForPreview,
                            $dateFrom,
                            $dateTo,
                            $col['dateRangeField']
                        );

                        $formsData = $formDataRepository->findBy(['id'=>$formsDataIds]);
                    }
                }

                if ($col['formData'] === 'daterange' && isset($col['formDataRange'][0], $col['formDataRange'][1])) {

                    $dateFrom = \DateTime::createFromFormat('m/d/Y', $col['formDataRange'][0]);
                    $dateTo = \DateTime::createFromFormat('m/d/Y', $col['formDataRange'][1]);

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

                if ($col['formData'] === 'all') {
                    $formsData = $formDataRepository->findBy([
                        'element_id' => $participantUserId,
                        'account_id' => $account,
                        'assignment' => $assignmentId,
                        'form'       => $formForPreview
                    ]);
                }

                if ($col['formData'] === 'latest') {
                    $formsData = $formDataRepository->findOneBy([
                        'element_id' => $participantUserId,
                        'account_id' => $account,
                        'assignment' => $assignmentId,
                        'form'       => $formForPreview
                    ], ['created_date' => 'DESC']);
                }

                $this->completedFormsTableService->setColumnsFilter($col['showFields']);
                if (!$formsData) {
                    $formPreviews[$col['name']] = [
                        'columns' => $this->completedFormsTableService->getColumns(),
                        'rows'    => [],
                        'name'    => $formForPreview->getName()
                    ];

                    continue;
                }

                $this->completedFormsTableService->setFormDataEntries($formsData);
                $formPreviews[$col['name']] = [
                    'columns' => $this->completedFormsTableService->getColumns(),
                    'rows'    => $this->completedFormsTableService->getRows(),
                    'name'    => $formForPreview->getName()
                ];
            }
        }

        return $formPreviews;
    }

}
