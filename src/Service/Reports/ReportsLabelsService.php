<?php

namespace App\Service\Reports;

use App\Entity\Forms;
use App\Domain\Form\FormSchemaHelper;
use Doctrine\ORM\EntityManagerInterface;

class ReportsLabelsService
{
    protected $em;
    protected $formHelper;

    public function __construct(EntityManagerInterface $entityManager, FormSchemaHelper $formHelper)
    {
        $this->em = $entityManager;
        $this->formHelper = $formHelper;
    }

    public function updateLabelsInReportsForForm(Forms $form)
    {
        $formHelper = $this->formHelper;
        $formHelper->setForm($form);
        $flattenColumns = $formHelper->getFlattenColumns();

        $newColumnsDescriptionsMap = [];

        foreach ($flattenColumns as $column) {
            $newColumnsDescriptionsMap[$column['name']] = $column['description'];
        }

        $reportsForms = $this->em->getRepository('App:ReportsForms')->findBy(['form' => $form]);

        foreach ($reportsForms as $reportsForm) {
            $report = $reportsForm->getReport();

            $reportData = json_decode($report->getData(), true);

            foreach ($reportData as $formIdx => $formFields) {
                foreach ($formFields['fields'] as $fieldIdx => $field) {
                    if (isset($newColumnsDescriptionsMap[$field['field']])) {
                        $reportData[$formIdx]['fields'][$fieldIdx]['label'] = $newColumnsDescriptionsMap[$field['field']];
                    }
                }
            }

            $report->setData(json_encode($reportData));
            $this->em->flush();
        }
    }
}
