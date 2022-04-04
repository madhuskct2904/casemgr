<?php

namespace App\Domain\Form;

use App\Entity\FormsData;
use App\Domain\FormValues\FormValuesParser;
use Doctrine\ORM\EntityManagerInterface;

class FormConditionsRender
{
    const STATES = [
        'equals'     => 1,
        'not_equals' => 2,
        'is_empty'   => 3,
        'not_empty'  => 4,
        'less_than'  => 5,
        'more_than'  => 6
    ];

    const COMPARE_AGAINST = [
        'value'         => 0,
        'another_field' => 1
    ];

    const ACTIONS = [
        'hide' => 0,
        'show' => 1
    ];

    protected $em;
    protected $formHelper;
    protected $completedFormValuesService;
    protected $formData;
    protected $namesValuesMap;
    protected $namesDisplayMap;
    protected $namesRawValuesMap;

    public function __construct(
        EntityManagerInterface $em,
        FormSchemaHelper $formHelper,
        FormValuesParser $completedFormValuesService
    )
    {
        $this->em = $em;
        $this->formHelper = $formHelper;
        $this->completedFormValuesService = $completedFormValuesService;
    }

    public function setFormData(FormsData $formData): self
    {
        $this->formData = $formData;
        return $this;
    }

    public function renderData($includeRaw = false): array
    {
        $data = [];

        $form = $this->formData->getForm();
        $formConditions = json_decode($form->getConditionals(), true);

        $this->mapFormData($includeRaw);

        foreach ($formConditions as $conditionEntry) {
            if ($this->conditionMet($conditionEntry)) {
                foreach ($conditionEntry['todo'] as $todo) {
                    $action = (int)$todo['action'];

                    if ($action === self::ACTIONS['hide']) {
                        $this->namesDisplayMap[$todo['field']] = false;
                    }

                    if ($action === self::ACTIONS['show']) {
                        $this->namesDisplayMap[$todo['field']] = true;
                    }
                }
            }
        }

        foreach ($this->namesValuesMap as $name => $value) {
            if ($this->namesDisplayMap[$name]) {

                $column = $this->formHelper->getColumnByName($name);

                $dataEntry = [
                    'name'  => $name,
                    'label' => $column['label'] ?? '',
                    'type'  => $column['type'] ?? '',
                    'title' => $column['description'] ?? '',
                    'grid'  => $column['grid'] ?? 12,
                    'value' => $value
                ];

                if ($includeRaw) {
                    $dataEntry['raw'] = $this->namesRawValuesMap[$column['name']];
                }

                $data[] = $dataEntry;
            }
        }

        return $data;
    }

    private function mapFormData($includeRaw): void
    {
        $completedFormValuesService = $this->completedFormValuesService;
        $completedFormValuesService->setCompletedFormData($this->formData);

        $formHelper = $this->formHelper;
        $formHelper->setForm($this->formData->getForm());
        $columns = $formHelper->getFlattenColumns();

        foreach ($columns as $column) {
            $this->namesDisplayMap[$column['name']] = !(bool)$column['hide'];
            $this->namesValuesMap[$column['name']] = $completedFormValuesService->getFieldValue($column);

            if ($includeRaw) {
                $this->namesRawValuesMap[$column['name']] = $completedFormValuesService->getFieldRawValue($column);
            }

            if ($column['type'] === 'text-entry') {
                $this->namesValuesMap[$column['name']] = $column['description'];
            }

            if ($column['type'] === 'shared-field') {
                $this->namesValuesMap[$column['name']] = $this->findValueBySharedField($column['name'], $this->formData);
            }
        }
    }

    private function findValueBySharedField(string $sharedFieldName, FormsData $formsData): ?string
    {
        $sharedField = $this->em->getRepository('App:SharedField')->findOneBy(['fieldName' => $sharedFieldName]);

        $fieldValue = $this->em->getRepository('App:FormsValues')->findByNameAndDataElementId(
            $sharedField->getSourceFieldName(),
            $formsData->getElementId()
        );

        return $fieldValue ? $fieldValue->getValue() : null;
    }

    private function conditionMet($conditionEntry)
    {
        $all = !empty($conditionEntry['all']) && $conditionEntry['all'] === 'true';
        $conditionMet = [];

        foreach ($conditionEntry['states'] as $conditionIdx => $condition) {

            $conditionMet[$conditionIdx] = false;

            if (!isset($this->namesValuesMap[$condition['field']])) {
                continue;
            }

            $firstValue = $this->namesValuesMap[$condition['field']];

            if ($condition['to'] == self::COMPARE_AGAINST['value']) {
                $secondValue = $condition['value'];
            }

            if ($condition['to'] == self::COMPARE_AGAINST['another_field']) {
                $secondValue = $this->namesValuesMap[$condition['field']];
            }

            if ($condition['state'] == self::STATES['equals'] && $firstValue == $secondValue) {
                $conditionMet[$conditionIdx] = true;
            }

            if ($condition['state'] == self::STATES['not_equals'] && $firstValue != $secondValue) {
                $conditionMet[$conditionIdx] = true;
            }

            if ($condition['state'] == self::STATES['is_empty'] && $firstValue == '') {
                $conditionMet[$conditionIdx] = true;
            }

            if ($condition['state'] == self::STATES['not_empty'] && $firstValue != '') {
                $conditionMet[$conditionIdx] = true;
            }

            if ($condition['state'] == self::STATES['less_than'] && filter_var($firstValue, FILTER_SANITIZE_NUMBER_FLOAT) < filter_var($secondValue, FILTER_SANITIZE_NUMBER_FLOAT)) {
                $conditionMet[$conditionIdx] = true;
            }

            if ($condition['state'] == self::STATES['more_than'] && filter_var($firstValue, FILTER_SANITIZE_NUMBER_FLOAT) > filter_var($secondValue, FILTER_SANITIZE_NUMBER_FLOAT)) {
                $conditionMet[$conditionIdx] = true;
            }

            if ($conditionMet[$conditionIdx] && !$all) {
                return true;
            }
        }

        if (count(array_unique($conditionMet)) === 1) {
            return current($conditionMet);
        }

        return false;
    }

}
