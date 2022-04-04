<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SharedField
 *
 * @ORM\Table(name="shared_field")
 * @ORM\Entity(repositoryClass="App\Repository\SharedFieldRepository")
 */
class SharedField
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var Forms
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Forms")
     * @ORM\JoinColumn(name="form_id", referencedColumnName="id", nullable=false)
     */

    private $form;

    /**
     * @var string
     *
     * @ORM\Column(name="field_name", type="string", length=50)
     */
    private $fieldName;

    /**
     * @var bool
     *
     * @ORM\Column(name="read_only", type="boolean")
     */
    private $readOnly;

    /**
     * @var Forms
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Forms")
     * @ORM\JoinColumn(name="source_form_id", referencedColumnName="id", nullable=false)
     */
    private $sourceForm;

    /**
     * @var string
     *
     * @ORM\Column(name="source_field_name", type="string", length=50)
     */
    private $sourceFieldName;

    /**
     * @var string
     *
     * @ORM\Column(name="source_form_data", type="string", length=50)
     */
    private $sourceFormData;

    /**
     * @var string
     *
     * @ORM\Column(name="source_field_function", type="string", length=50)
     */
    private $sourceFieldFunction;

    /**
     * @var string
     *
     * @ORM\Column(name="source_form_data_range", type="string", length=50)
     */
    private $sourceFormDataRange;


    /**
     * @var string
     *
     * @ORM\Column(name="source_field_type", type="string", length=30)
     */
    private $sourceFieldType;

    /**
     * @var string
     *
     * @ORM\Column(name="source_field_value", type="text")
     */
    private $sourceFieldValue;

    /**
     * @var string
     *
     * @ORM\Column(name="date_range_field", type="string", length=30, nullable=true)
     */
    private $dateRangeField;

    /**
     * SharedField constructor.
     */
    public function __construct()
    {
        $this->sourceFieldValue = '';
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return Forms
     */
    public function getForm(): Forms
    {
        return $this->form;
    }

    /**
     * @param Forms $form
     */
    public function setForm(Forms $form): void
    {
        $this->form = $form;
    }

    /**
     * @return string
     */
    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    /**
     * @param string $fieldName
     */
    public function setFieldName(string $fieldName): void
    {
        $this->fieldName = $fieldName;
    }

    /**
     * @return bool
     */
    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * @param bool $readOnly
     */
    public function setReadOnly(bool $readOnly): void
    {
        $this->readOnly = $readOnly;
    }

    /**
     * @return Forms
     */
    public function getSourceForm(): Forms
    {
        return $this->sourceForm;
    }

    /**
     * @param Forms $sourceForm
     */
    public function setSourceForm(Forms $sourceForm): void
    {
        $this->sourceForm = $sourceForm;
    }

    /**
     * @return string
     */
    public function getSourceFieldName(): string
    {
        return $this->sourceFieldName;
    }

    /**
     * @param string $sourceFieldName
     */
    public function setSourceFieldName(string $sourceFieldName): void
    {
        $this->sourceFieldName = $sourceFieldName;
    }

    /**
     * @return string
     */
    public function getSourceFormData(): string
    {
        return $this->sourceFormData;
    }

    /**
     * @param string $sourceFormData
     */
    public function setSourceFormData(?string $sourceFormData): void
    {
        $this->sourceFormData = $sourceFormData;
    }

    /**
     * @return string
     */
    public function getSourceFieldFunction(): ?string
    {
        return $this->sourceFieldFunction;
    }

    /**
     * @param string $sourceFieldFunction
     */
    public function setSourceFieldFunction(?string $sourceFieldFunction): void
    {
        $this->sourceFieldFunction = $sourceFieldFunction;
    }

    /**
     * @return string
     */
    public function getSourceFormDataRange(): array
    {
        return json_decode($this->sourceFormDataRange ?? [], true);
    }

    /**
     * @param string $sourceFormDataRange
     */
    public function setSourceFormDataRange(?array $sourceFormDataRange): void
    {
        $this->sourceFormDataRange = json_encode($sourceFormDataRange ?? []);
    }

    /**
     * @return string
     */
    public function getSourceFieldType(): string
    {
        return $this->sourceFieldType;
    }

    /**
     * @param string $sourceFieldType
     */
    public function setSourceFieldType(string $sourceFieldType): void
    {
        $this->sourceFieldType = $sourceFieldType;
    }

    /**
     * @return string
     */
    public function getSourceFieldValue(): string
    {
        return $this->sourceFieldValue;
    }

    /**
     * @param string $sourceFieldValue
     */
    public function setSourceFieldValue(string $sourceFieldValue): void
    {
        $this->sourceFieldValue = $sourceFieldValue;
    }

    public function getDateRangeField(): ?string
    {
        return $this->dateRangeField;
    }

    public function setDateRangeField(?string $dateRangeField): void
    {
        $this->dateRangeField = $dateRangeField;
    }
}
