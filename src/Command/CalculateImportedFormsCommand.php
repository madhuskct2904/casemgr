<?php

namespace App\Command;

use App\Entity\Forms;
use App\Entity\FormsValues;
use App\Service\FormCalculations;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CalculateImportedFormsCommand extends Command
{
    protected ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine, FormCalculations $calculations)
    {
        $this->doctrine = $doctrine;
        $this->calculations = $calculations;
        parent::__construct();

    }

    protected function configure()
    {
        $this
            ->setName('app:import:calculate-forms')
            ->setDescription('Calculate imported forms');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $entityManager = $this->doctrine->getManager();

        $imports = $entityManager->getRepository('App:Imports')->findAll();

        $formIds = [];

        foreach ($imports as $import) {
            $formIds[] = $import->getForm()->getId();
        }

        $forms = $entityManager->getRepository('App:Forms')->findBy(
            [
                'id' => array_unique($formIds)
            ]
        );

        foreach ($forms as $form) {
            $updateValues = [];

            $formsData = $entityManager->getRepository('App:FormsData')
                ->createQueryBuilder('fd')
                ->where('fd.form = :form')
                ->setParameter('form', $form)
                ->getQuery()
                ->getResult();

            foreach ($formsData as $formData) {
                $values = $formData->getValues();
                $formValues = [];

                foreach ($values as $value) {
                    $formValues[$value->getName()] = $value->getValue();
                }

                $result = $this->calculateFormValues($form, $formValues);

                $updateValues = array_diff_assoc($result, $formValues);

                foreach ($updateValues as $name => $newValue) {
                    $formValue = $entityManager->getRepository('App:FormsValues')->findOneBy(
                        [
                            'data' => $formData,
                            'name' => $name
                        ]
                    );

                    if ($formValue) {
                        $formValue->setValue($newValue);
                        $entityManager->flush();
                    } else {
                        $formValue = new FormsValues();
                        $formValue->setData($formData);
                        $formValue->setName($name);
                        $formValue->setValue($newValue);
                        $formValue->setDate(new \DateTime());
                        $entityManager->persist($formValue);
                        $entityManager->flush();
                    }
                }
            }

            if (count($updateValues)) {
                $output->writeln('Updated fields for form: ' . $form->getId() . ': ');
                $output->writeln(dump($updateValues));
            } else {
                $output->writeln(('No fields were updated for form: ' . $form->getId() . '.'));
            }
        }
    }

    private function calculateFormValues(Forms $form, $formDataValuesArray)
    {
        $this->calculations->setCalculations(json_decode($form->getCalculations(), true));

        $fields = $this->formDataFields($form);

        $this->calculations->setFields($fields);
        $this->calculations->setData($formDataValuesArray);

        return $this->calculations->calculate();
    }

    private function formDataFields(Forms $form)
    {
        $array = json_decode($form->getData(), true);

        // accordion values as fields
        foreach ($array as $k => $field) {
            if ($field['type'] === 'accordion') {
                foreach ($field['values'] as $accordionField) {
                    array_push($array, $accordionField);
                }
                unset($array[$k]);
            }
        }

        return $array;
    }
}
