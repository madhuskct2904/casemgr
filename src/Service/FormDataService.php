<?php

namespace App\Service;

use App\Entity\FormsData;
use Doctrine\ORM\EntityManagerInterface;

class FormDataService
{
    protected $em;
    protected $formData;
    protected $valuesMap = null;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function setFormData(FormsData $formsData): self
    {
        $this->formData = $formsData;
        $this->valuesMap = null;
        return $this;
    }

    public function mapValues()
    {
        $map = json_decode($this->formData->getForm()->getColumnsMap(), true);

        $fieldsMap = [];

        foreach ($map as $mapRow) {
            $fieldsMap[$mapRow['name']] = $mapRow['value'];
        }

        $formValuesMap = [];

        foreach ($this->formData->getValues() as $value) {
            $formValuesMap[$value->getName()] = $value->getValue();
        }

        foreach ($fieldsMap as $fieldName => $fieldValue) {
            if (isset($formValuesMap[$fieldValue])) {
                $this->valuesMap[$fieldName] = $formValuesMap[$fieldValue];
            }
        }

    }

    public function getMappedValue(string $name): ?string
    {
        if ($this->valuesMap === null) {
            $this->mapValues();
        }

        return $this->valuesMap[$name] ?? null;
    }

    public function getFormDataAsArr(): array
    {
        $formData = $this->formData;
        $form = $formData->getForm();
        $userRepo = $this->em->getRepository('App:Users');

        $participant = null;

        if ($form->getModule()->getGroup() !== 'organization') {
            $participant = $userRepo->findOneBy([
                'id'   => $formData->getElementId(),
                'type' => 'participant'
            ]);
        }

        $manager = '';
        $secondaryManager = '';

        if ($participant && $formData->getManager() && $managerData = $formData->getManager()->getData()) {
            $manager = $managerData->getFullName(false);
        }

        if ($participant && !empty($participant->getData()->getCaseManagerSecondary())) {
            $secondaryManager = $userRepo->findOneBy(['id' => $participant->getData()->getCaseManagerSecondary()]);

            if ($secondaryManager && $secondaryManager->getData()) {
                $secondaryManager = $secondaryManager->getData()->getFullName(false);
            }
        }

        $formData = [
            'id'                     => $formData->getId(),
            'creator'                => $formData->getCreator() ? $formData->getCreator()->getData()->getFullName() : 'System Administrator',
            'created_date'           => $formData->getCreatedDate(),
            'editor'                 => $formData->getEditor() ? $formData->getEditor()->getData()->getFullName() : 'System Administrator',
            'updated_date'           => $formData->getUpdatedDate(),
            'case_manager'           => $manager ?: null,
            'secondary_case_manager' => $secondaryManager ?: null,
            'participant'            => $participant ? $participant->getData()->getFullName() : null,
        ];

        return $formData;
    }
}
