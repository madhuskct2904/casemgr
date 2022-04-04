<?php

namespace App\Domain\DataImport;

class ImportFormValidator implements ImportValidatorInterface
{
    private $fields;
    private $map;
    private $participantIdRequired;
    private $ignoreRequired;
    private $row = [];
    private $rowIsValid = true;
    private $actions = [
        'checkbox'       => 'Checkbox',
        'checkbox-group' => 'CheckboxGroup',
        'radio-group'    => 'NoAction',
        'text'           => 'Text',
        'email'          => 'Text',
        'password'       => 'Text',
        'textarea'       => 'Text',
        'select'         => 'NoAction',
        'file'           => false,
        'date'           => 'Date',
        'header'         => false,
        'signature'      => false,
        'image-upload'   => false,
        'divider'        => false,
        'row'            => false,
        'time'           => 'Time',
        'number'         => 'Number',
        'accordion'      => false,
        'rating'         => 'NoAction',
        'address'        => 'NoAction',
        'currency'       => 'Currency',
        'systemid'       => false,
        'select2'        => 'CaseManager',
    ];

    public function __construct(array $fields, array $map, $participantIdRequired = true, $ignoreRequired = false)
    {
        $this->fields = $fields;
        $this->map = $map;
        $this->participantIdRequired = $participantIdRequired;
        $this->ignoreRequired = $ignoreRequired;
    }

    public function validateRow($row): ImportValidatorInterface
    {
        $types = array_keys($this->actions);
        $this->row = [];
        $map = [];
        $emptyColumn = [
            'value'  => '',
            'colIdx' => null
        ];

        foreach ($row as $colIdx => $colValue) {
            if (isset($this->map[$colIdx])) {
                $map[$this->map[$colIdx]] = [
                    'value'  => $colValue,
                    'colIdx' => $colIdx
                ];
            }
        }

        foreach ($this->fields as $field) {

            if (!in_array($field['type'], $types) || $this->actions[$field['type']] === false) {
                continue;
            }

            $this->validateField($field, $map[$field['name']] ?? $emptyColumn, $this->ignoreRequired);
        }

// custom fields
//        if ($this->participantIdRequired && isset($map['import-pid'])) {
//            $this->validateImportPid($map['import-pid']);
//        }

//        if (isset($map['import-completed-date'])) {
//            $this->validateImportCompletedDate($map['import-completed-date']);
//        }

        return $this;
    }

    public function isValid(): bool
    {
        return $this->rowIsValid;
    }

    public function getRow(): array
    {
        return $this->row;
    }

    private function validateField(array $field, array $csvColumn, bool $ignoreRequiredRule = false): void
    {
        $fieldName = $field['name'];
        $fieldType = $field['type'];
        $value = $csvColumn['value'];

        $colValue = [
            'column' => $csvColumn['colIdx'] ?? null,
            'field'  => $fieldName,
            'name'   => $field['label'],
            'value'  => $value,
        ];

        if (isset($this->row[$fieldName])) {
            $this->row[$fieldName] = $colValue;
            return;
        }

        $action = 'validate' . $this->actions[$fieldType];

        // options
        if ($fieldType !== 'checkbox-group'
            && isset($field['values'])
            && $field['values']
            && strlen($value)
            && false !== $error = $this->validateOptions($value, $field)) {
            $colValue['message'] = $error;
            $this->rowIsValid = false;
        }

        // types
        if (!isset($this->row[$fieldName]) && strlen($value) && false !== $error = $this->$action($value, $field)) {
            $colValue['message'] = $error;
            $this->rowIsValid = false;
        }

        // required
        if (!$this->ignoreRequired
            && !isset($this->row[$fieldName])
            && $field['required']
            && false !== $error = $this->validateRequired($value)) {
            $colValue['message'] = $error;
            $this->rowIsValid = false;
        }

        $this->row[$fieldName] = $colValue;
    }

    /**
     * @param $value
     * @return bool|string
     */
    private function validateRequired($value)
    {
        return strlen($value) ? false : 'required value';
    }

    /**
     * @param $value
     * @param $field
     * @return bool|string
     */
    private function validateOptions($value, $field)
    {
        return in_array($value, array_column(
            $field['values'],
            $field['type'] === 'rating' ? 'value' : 'label'
        )) ? false : 'invalid options value';
    }

    /**
     * parsed before validation
     * number_format decimals: 2, dec_point: '.', thousands_sep: ','
     *
     * @param $value
     * @return bool|string
     */
    private function validateCurrency($value)
    {
        //return preg_match('/^\$\s{1}\d+(.\d{2})?$/', $value) ? false : 'invalid currency value';
        return false;
    }

    /**
     * value equals label
     *
     * @param $value
     * @param $field
     * @return bool|string
     */
    private function validateCheckbox($value, $field)
    {
        return $value === $field['label'] ? false : 'invalid checkbox value';
    }

    /**
     * @param $value
     * @param $field
     * @return bool|string
     */
    private function validateCheckboxGroup($value, $field)
    {
        $values = explode(';', $value);
        $error = false;

        $fieldValues = array_column($field['values'], 'label');

        foreach ($values as $v) {
            if (!in_array($v, $fieldValues)) {
                $error = 'invalid checkbox-group value: ' . $value;
                break;
            }
        }

        return $error;
    }

    /**
     * value format: mm-dd-yyyy eg. 12/31/2018
     */
    private function validateDate(string $value)
    {
        $values = explode('/', $value);
        $error = 'invalid date value : ' . $value;

        if (count($values) === 3 && strlen($values[0]) === 2 && strlen($values[1]) === 2 && strlen($values[2]) === 4) {
            return checkdate((int)$values[0], (int)$values[1], (int)$values[2]) ? false : $error;
        }

        return $error;
    }

    private function validateTime(string $value)
    {
        return preg_match('/^(1|2|3|4|5|6|7|8|9|10|11|12):(00|15|30|45)\s{1}(AM|PM)$/', $value) ? false : 'invalid time value';
    }

    private function validateNumber($value)
    {
        return is_numeric($value) ? false : 'invalid number value';
    }

    private function validateText($value, $field)
    {
        if (isset($field['maxSize']) && (int)$field['maxSize'] && strlen($value) > (int)$field['maxSize']) {
            return 'too long text value';
        }

        return false;
    }

    private function validateCaseManager($value)
    {
        return is_numeric($value) ? false : 'invalid number value';
    }

    private function validateImportPid(array $pidColumn)
    {
        $this->row['import-pid'] = [
            'column'  => $pidColumn['colIdx'] ?? null,
            'field'   => 'import-pid',
            'name'    => 'System ID',
            'message' => 'participant required'
        ];

        return false;
    }

    private function validateImportCompletedDate(array $completedDateColumn)
    {
        $error = $this->validateDate($completedDateColumn['value']);

        if (!$error) {
            return false;
        }

        $this->row['import-completed-date'] = [
            'column'  => $completedDateColumn['colIdx'] ?? null,
            'field'   => 'import-completed-date',
            'name'    => 'Completed date',
            'message' => $error
        ];
    }

    private function validateNoAction(): bool
    {
        return false;
    }
}
