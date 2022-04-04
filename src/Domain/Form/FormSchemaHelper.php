<?php

namespace App\Domain\Form;

use App\Entity\Forms;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;

class FormSchemaHelper
{
    protected $form;
    protected $entityManager;
    protected $flattenColumns;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function setForm(Forms $form): self
    {
        $this->form = $form;
        $this->flattenFormColumns();
        return $this;
    }

    public function getFlattenColumns(): array
    {
        return $this->flattenColumns;
    }


    public function getColumnByName(string $name): ?array
    {
        $columns = $this->flattenColumns;

        foreach ($columns as $key => $column) {
            if (isset($column['name']) && $column['name'] == $name) {
                return $this->flattenColumns[$key];
            }
        }

        return null;
    }

    public static function getLabelForValue(array $formColumn, string $value): string
    {
        if (!isset($formColumn['values'])) {
            return '';
        }

        foreach ($formColumn['values'] as $formColumnValue) {
            if (isset($formColumnValue['value']) && $formColumnValue['value'] == $value && isset($formColumnValue['label'])) {
                return $formColumnValue['label'];
            }
        }

        return '';
    }

    public static function getValueForLabel(array $formColumn, string $label): string
    {
        if (!isset($formColumn['values'])) {
            return '';
        }

        foreach ($formColumn['values'] as $formColumnValue) {
            if (isset($formColumnValue['label']) && $formColumnValue['label'] == $label && isset($formColumnValue['value'])) {
                return $formColumnValue['value'];
            }
        }

        return '';
    }

    public static function flattenColumns(array $columns): array
    {
        $flatten = [];

        foreach ($columns as $column) {
            if (isset($column['type']) && $column['type'] == 'accordion') {
                $flatten = array_merge($flatten, $column['values']);
                continue;
            }
            $flatten[] = $column;
        }

        return $flatten;
    }

    private function flattenFormColumns(): array
    {
        $columns = json_decode($this->form->getData(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->flattenColumns = [];
            return [];
        }

        $this->flattenColumns = self::flattenColumns($columns);
        return $this->flattenColumns;
    }

    public static function getColumnOptions(Forms $forms, string $columnName)
    {
        $columns = self::flattenColumns(json_decode($forms->getData(), true));

        foreach ($columns as $column) {
            if ($column['name'] !== $columnName) {
                continue;
            }
            if (!isset($column['values'])) {
                return [];
            }
            return $column['values'];
        }
    }

    public function getFlattenColumnsForForm(Forms $form): array
    {
        try {
            $columns = json_decode($form->getData(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception){
            return [];
        }

        return self::flattenColumns($columns);
    }
}
