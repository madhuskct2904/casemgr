<?php

namespace App\Service;

/**
 * Class FormCalculations
 *
 * Calculates form data values for data array
 */
class FormCalculations
{
    private $calculations;
    private $fields;
    private $data;

    const ADD = 1;
    const SUBTRACT = 2;
    const MULTIPLY = 3;
    const DIVIDE = 4;
    const PERCENTAGE_OF = 5;
    const ADD_YEARS = 1;
    const SUBTRACT_YEARS = 2;
    const ADD_DAYS = 11;
    const SUBTRACT_DAYS = 12;

    /**
     * Calculations array from Form entity
     * @param array $calculations
     */
    public function setCalculations(array $calculations)
    {
        $this->calculations = $calculations;
    }

    /**
     * Form schema
     * @param array $fields
     */
    public function setFields(array $fields)
    {
        $this->fields = array_values($fields);
    }

    /**
     * Data as field name => field value
     * @param array $data
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * Return array of all fiedls values
     * @return array
     */
    public function calculate()
    {
        foreach ($this->calculations as $calculation) {
            if (!isset($calculation['displayIn'])) {
                continue;
            }

            $first = 0;

            foreach ($calculation['questions'] as $questionIdx => $question) {

                if ($questionFieldIndex = array_search($question['field'], array_column($this->fields, 'name')) !== false) {
                    $questionField = $this->fields[$questionFieldIndex];

                    if ($questionIdx === 0) { // first question, the $first is the first field value. Otherwise $first is result of previous calculation or 0 if not defined
                        $first = isset($this->data[$question['field']]) ? $this->data[$question['field']] : 0;
                    } else {
                        // not first iteration, so we can do calculations
                        $second = isset($this->data[$question['field']]) ? $this->data[$question['field']] : 0;
                        $calcType = (int)$calculation['questions'][$questionIdx - 1]['calculation'];

                        if ($questionField['type'] === 'date') {

                            $first = 0; // set default value as 0, in case if selected fields has wrong dates

                            if (($amountDate = \DateTime::createFromFormat('m/d/Y', $first)) && ($valueDate = \DateTime::createFromFormat('m/d/Y', $second))) {
                                if ($calcType == self::ADD_YEARS) {
                                    $first = $valueDate->diff($amountDate)->format('%r%y');
                                }

                                if ($calcType == self::SUBTRACT_YEARS) {
                                    $first = $valueDate->diff($amountDate)->format('%r%y');
                                }

                                if ($calcType == self::ADD_DAYS) {
                                    $first = $valueDate->diff($amountDate)->format('%r%a');
                                }

                                if ($calcType == self::SUBTRACT_DAYS) {
                                    $first = $valueDate->diff($amountDate)->format('%r%a');
                                }
                            }
                            continue;
                        }

                        if ($questionField['type'] === 'currency') {
                            $first = filter_var($first, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                        }

                        $first = $this->process($first, $second, $calcType);
                    }
                    continue;
                }

                if ($question['field'] === 'custom-value') {

                    if ($questionIdx === 0) {
                        $first = $question['value'];
                        continue;
                    }

                    $calcType = (int)$calculation['questions'][$questionIdx - 1]['calculation'];
                    $second = $question['value'];

                    if ($second < 0) {
                        $second -= $second;
                    }

                    if (($amountDate = \DateTime::createFromFormat('m/d/Y', $first)) && in_array($calcType, [1, 2, 11, 12])) {
                        $first = 0;

                        if ($calcType == self::ADD_YEARS) {
                            $first = $amountDate->add(new \DateInterval('P' . $second . 'Y'))->format('m/d/Y');
                        }
                        if ($calcType == self::SUBTRACT_YEARS) {
                            $first = $amountDate->sub(new \DateInterval('P' . $second . 'Y'))->format('m/d/Y');
                        }
                        if ($calcType == self::ADD_DAYS) {
                            $first = $amountDate->add(new \DateInterval('P' . $second . 'D'))->format('m/d/Y');
                        }
                        if ($calcType == self::SUBTRACT_DAYS) {
                            $first = $amountDate->sub(new \DateInterval('P' . $second . 'D'))->format('m/d/Y');
                        }

                        continue;
                    }

                    $first = $this->process($first, $second, $calcType);

                }
            }

            if (strpos($calculation['displayIn'], 'currency') === 0) {
                $first = is_string($first) ? floatval($first) : $first;

                $this->data[$calculation['displayIn']] = '$ ' . number_format($first, 2);
            } else {
                $this->data[$calculation['displayIn']] = $first;
            }
        }

        return $this->data;
    }

    /**
     * @param $first
     * @param $second
     * @param $calcType
     * @return float|int
     */
    private function process($first, $second, $calcType)
    {
        $first = filter_var($first, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $second = filter_var($second, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

        switch ($calcType) {
            case self::ADD:
                return (float)$first + (float)$second;
            case self::SUBTRACT:
                return (float)$first - (float)$second;
            case self::MULTIPLY:
                return (float)$first * (float)$second;
            case self::DIVIDE:
                return (float)$second != 0 ? (float)$first / (float)$second : 0;
            case self::PERCENTAGE_OF:
                return (int)$second != 0 ? (int)$first / (int)$second * 100 : 0;
            case self::ADD_DAYS:
            case self::SUBTRACT_DAYS:
                return (int)$first + (int)$second;
            default:
                return 0;
        }
    }
}
