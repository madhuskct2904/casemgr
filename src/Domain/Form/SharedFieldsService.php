<?php


namespace App\Domain\Form;

use App\Domain\FormValues\FormValuesMathHelper;
use App\Entity\Accounts;
use App\Entity\Forms;
use App\Entity\SharedField;
use Doctrine\ORM\EntityManagerInterface;

class SharedFieldsService
{
    private $em;
    private $formHelper;

    public function __construct(EntityManagerInterface $entityManager, FormSchemaHelper $formHelper)
    {
        $this->em = $entityManager;
        $this->formHelper = $formHelper;
    }

    public function getSharedFields(Forms $form): array
    {
        return $this->em->getRepository('App:SharedField')->findForForm($form);
    }

    public function getSharedFieldsValues(Forms $form, Accounts $account, ?int $participantUserId = null, ?int $assignmentId = null): array
    {
        $sharedFields = array_keys($this->getSharedFields($form));

        $values = [];

        foreach ($sharedFields as $sharedFieldName) {
            $sharedField = $this->em->getRepository('App:SharedField')->findOneBy(['fieldName' => $sharedFieldName, 'form' => $form]);

            if (!$sharedField) {
                throw new \Exception('Shared field not found!');
            }

            $values[$sharedFieldName] = $this->getSharedFieldValue($sharedField, $account, $participantUserId, $assignmentId);
        }

        return $values;
    }

    public function updateSharedFields(Forms $form, array $sharedFields, string $dateFormat = 'm/d/Y'): void
    {
        // update fields shared from another forms

        $existingSharedFields = $this->em->getRepository('App:SharedField')->findBy(['form' => $form]);
        $sharedFieldsNames = array_keys($sharedFields);

        foreach ($existingSharedFields as $existingSharedField) {
            if (!in_array($existingSharedField->getFieldName(), $sharedFieldsNames)) {
                $this->em->remove($existingSharedField);
            }
        }

        foreach ($sharedFields as $sharedFieldName => $sharedFieldData) {
            $sharedField = $this->em->getRepository('App:SharedField')->findOneBy(['fieldName' => $sharedFieldName, 'form' => $form]);

            if (!$sharedField) {
                $sharedField = new SharedField();
                $sharedField->setForm($form);
                $sharedField->setFieldName($sharedFieldName);
            }

            if (!isset($sharedFieldData['sourceFormData'], $sharedFieldData['sourceFieldName'])) {
                $this->em->remove($sharedField);
                $this->em->flush();
                continue;
            }

            $sourceForm = $this->em->getRepository('App:Forms')->find($sharedFieldData['sourceFormId']);

            $dateRange = null;

            if ($sharedFieldData['sourceFormData'] === 'daterange' || $sharedFieldData['sourceFormData'] === 'daterange-field') {
                $dateFrom = \DateTime::createFromFormat($dateFormat, $sharedFieldData['sourceFormDataRange'][0]);
                $dateTo = \DateTime::createFromFormat($dateFormat, $sharedFieldData['sourceFormDataRange'][1]);

                if ($dateFrom && $dateTo) {
                    $dateRange = [$dateFrom->format('m/d/Y'), $dateTo->format('m/d/Y'), $dateFormat];
                }
            }

            $sharedField->setSourceForm($sourceForm);
            $sharedField->setSourceFieldName($sharedFieldData['sourceFieldName']);
            $sharedField->setSourceFieldFunction($sharedFieldData['sourceFieldFunction'] ?? null);
            $sharedField->setSourceFormData($sharedFieldData['sourceFormData']);
            $sharedField->setSourceFormDataRange($dateRange ?? null);
            $sharedField->setSourceFieldType($sharedFieldData['sourceFieldType']);
            $sharedField->setReadOnly($sharedFieldData['sourceFormData'] !== 'last' || $sharedFieldData['readOnly']);
            $sharedField->setSourceFieldValue($sharedFieldData['sourceFieldValue'] ?? '');
            $sharedField->setDateRangeField($sharedFieldData['dateRangeField'] ?? null);

            $this->em->persist($sharedField);
            $this->em->flush();
        }
    }

    public function updateSharedFieldsValuesForForm(Forms $form, $limit = null, $offset = null)
    {
        $sharedFields = $this->em->getRepository('App:SharedField')->findBy(['form' => $form]);
        $formDataEntries = $this->em->getRepository('App:FormsData')->findBy(['form' => $form], null, $limit, $offset);

        foreach ($sharedFields as $sharedField) {
            $this->updateSharedFieldRelatedValues($sharedField, $formDataEntries);
        }
    }

    private function updateSharedFieldRelatedValues(SharedField $sharedField, $formDataEntries)
    {
        if (!count($formDataEntries)) {
            return;
        }

        $conn = $this->em->getConnection();
        $fieldName = $sharedField->getFieldName();

        $fdIds = '(';

        foreach ($formDataEntries as $formDataEntry) {
            $fdIds .= $formDataEntry->getId() . ',';
        }

        $fdIds = rtrim($fdIds, ',') . ')';

        $sql = "DELETE FROM forms_values WHERE name = '$fieldName' AND data_id IN $fdIds";
        $conn->exec($sql);

        foreach ($formDataEntries as $formData) {
            $value = $this->getSharedFieldValue($sharedField, $formData->getAccount(), $formData->getElementId(), $formData->getAssignment() ? $formData->getAssignment()->getId() : null);
            if ($value) {
                $dataId = $formData->getId();
                $sql = "INSERT INTO forms_values (name, value, data_id) VALUES ('$fieldName', '$value', $dataId)";
                $conn->exec($sql);
            }
        }
    }


    public function getSharedFieldValue(SharedField $sharedField, Accounts $account, ?int $participantUserId = null, ?int $assignmentId = null): ?string
    {
        $dataIds = [];

        $formDataRepository = $this->em->getRepository('App:FormsData');
        $valuesRepository = $this->em->getRepository('App:FormsValues');

        if ($sharedField->getSourceFormData() === 'daterange-field' && $sharedField->getDateRangeField()) {

            $fieldName = $sharedField->getDateRangeField();

            $dateFrom = \DateTime::createFromFormat('m/d/Y', $sharedField->getSourceFormDataRange()[0]);
            $dateTo = \DateTime::createFromFormat('m/d/Y', $sharedField->getSourceFormDataRange()[1]);

            $dataIds = $formDataRepository->findForParticipantAssignmentAccountFieldDateRange(
                $participantUserId,
                $assignmentId,
                $account,
                $sharedField->getSourceForm(),
                $dateFrom,
                $dateTo,
                $fieldName
            );
        }

        if ($sharedField->getSourceFormData() === 'daterange') {
            $dateFrom = \DateTime::createFromFormat('m/d/Y', $sharedField->getSourceFormDataRange()[0]);
            $dateTo = \DateTime::createFromFormat('m/d/Y', $sharedField->getSourceFormDataRange()[1]);

            $dataIds = $formDataRepository->findForParticipantAssignmentAccountDateRange(
                $participantUserId,
                $assignmentId,
                $account,
                $sharedField->getSourceForm(),
                $dateFrom,
                $dateTo
            );
        }

        if ($sharedField->getSourceFormData() === 'all') {
            $dataIds = $formDataRepository->findBy([
                'element_id' => $participantUserId,
                'account_id' => $account,
                'assignment' => $assignmentId,
                'form'       => $sharedField->getSourceForm()
            ]);
        }

        if ($sharedField->getSourceFormData() === 'last') {
            $dataIds = [
                $formDataRepository->findOneBy([
                    'element_id' => $participantUserId,
                    'account_id' => $account,
                    'assignment' => $assignmentId,
                    'form'       => $sharedField->getSourceForm()
                ], ['created_date' => 'DESC'])
            ];
        }

        if ($sharedField->getSourceFieldFunction() === 'avg') {
            $values = $valuesRepository->findByNameAndDataIds($sharedField->getSourceFieldName(), $dataIds);

            if (!$values) {
                return null;
            }

            return $this->parseResult($sharedField, FormValuesMathHelper::avgValues($values, $sharedField->getSourceFieldName()));
        }

        if ($sharedField->getSourceFieldFunction() === 'sum') {
            $values = $valuesRepository->findByNameAndDataIds($sharedField->getSourceFieldName(), $dataIds);

            if (!$values) {
                return null;
            }

            return $this->parseResult($sharedField, FormValuesMathHelper::sumValues($values, $sharedField->getSourceFieldName()));
        }


        if ($sharedField->getSourceFieldFunction() === 'count') {
            if ($sharedField->getSourceFieldValue() === '__all') {
                $values = $valuesRepository->findByNameAndDataIds($sharedField->getSourceFieldName(), $dataIds);

                $options = FormSchemaHelper::getColumnOptions($sharedField->getSourceForm(), $sharedField->getSourceFieldName());

                $allValues = ['[No value]' => 0];

                foreach ($options as $option) {
                    $allValues[$option['label']] = 0;
                }

                foreach ($values as $value) {
                    if ($value === '') {
                        $value = '[No value]';
                    }

                    $allValues[$value] = isset($allValues[$value]) ? ++$allValues[$value] : 0;
                }

                return json_encode($allValues);
            }

            return $valuesRepository->findByNameValueAndDataIds($sharedField->getSourceFieldName(), $sharedField->getSourceFieldValue(), $dataIds);
        }


        $values = $valuesRepository->findByNameAndDataIds($sharedField->getSourceFieldName(), $dataIds);

        if (count($values)) {
            return $values[0];
        }

        return null;
    }

    private function parseResult($sharedField, $value): string
    {
        if ($sharedField->getSourceFieldType() == 'currency') {
            return '$ ' . $value;
        }

        return $value;
    }
}
