<?php


namespace App\Domain\FormValues;

class FormValuesMathHelper
{
    public static function sumValues(array $values, string $fieldName)
    {
        $numericValues = array_map(function ($item) {
            return filter_var($item, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }, $values);

        $sum = round(array_sum($numericValues), 2);

        return self::formatFieldValue($fieldName, $sum);
    }

    public static function avgValues(array $values, string $fieldName)
    {
        $numericValues = array_map(function ($item) {
            return filter_var($item, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        }, $values);

        $avg = round(array_sum($numericValues) / count($values), 2);

        return self::formatFieldValue($fieldName, $avg);
    }

    private static function formatFieldValue(string $fieldName, $value)
    {
        if (strpos($fieldName, '_currency') !== false) {
            if ($value === '') {
                $value = (float)$value;
            }

            return '$ ' . number_format($value, 2, '.', ',');
        }

        return $value;
    }
}
