<?php

namespace App\Command;

use App\Domain\Form\FormSchemaHelper;
use App\Enum\FormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckFormsValuesCommand extends Command
{
    protected ManagerRegistry $doctrine;
    protected FormSchemaHelper $formSchemaHelper;

    public function __construct(
        ManagerRegistry $doctrine,
        FormSchemaHelper $formSchemaHelper
    )
    {
        $this->doctrine = $doctrine;
        $this->formSchemaHelper = $formSchemaHelper;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:check-forms-values')
            ->setDescription('Check for invalid values in forms, generate CSV');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $forms = $this->doctrine->getRepository('App:Forms')->findBy([], ['name' => 'ASC']);

        $invalidValues = [];

        foreach ($forms as $form) {
            $wrong = false;

            $output->writeln('"Form: ' . $form->getName() . '","' . $form->getId() . '"');
            $output->writeln('"Present in organizations: "');
            $output->writeln('""');

            foreach ($form->getAccounts() as $organization) {
                $output->writeln('"' . $organization->getOrganizationName() . '","' . $organization->getId() . '"');
            }

            if ($form->getType() != FormType::FORM) {
                continue;
            }

            $this->formSchemaHelper->setForm($form);
            $fields = $this->formSchemaHelper->getFlattenColumns();

            $formId = $form->getId();
            $formName = $form->getName();

            $invalidValues[$formId . ' - ' . $formName] = [];


            foreach ($fields as $field) {
                if (!isset($field['name'])) {
                    continue;
                }

                if (!isset($field['values'])) {
                    continue;
                }

                $fieldName = $field['name'];
                $fieldDescription = $field['description'];
                $valuesStr = '(';


                foreach ($field['values'] as $value) {

//                    check for single field
//                    if($field['name'] != 'select-1529598528651') {
//                        continue 2;
//                    }

                    if ($field['type'] == 'rating') {
                        $searchFor = str_replace("'", "\'", $value['value']);
                    } else {
                        $searchFor = str_replace("'", "\'", $value['label']);
                    }

                    $valuesStr .= "'" . $searchFor . "',";
                }

                $valuesStr = rtrim($valuesStr, ',');
                $valuesStr .= ')';

                $connection = $this->doctrine->getManager()->getConnection();
                $values = $connection->fetchAllAssociative("SELECT distinct(fv.id), fv.value FROM forms_values fv JOIN forms_data fd ON fv.data_id = fd.id JOIN forms f ON fd.form_id = f.id
JOIN forms_history fh ON f.id = fh.form_id
WHERE f.id = $formId and fv.name = '$fieldName' AND (fv.value NOT IN $valuesStr AND fv.value != \"\")");


                if (count($values)) {
                    $output->writeln('"Potential invalid field: ' . '[' . $fieldName . '] ' . $fieldDescription . '"');
                    $output->writeln('""');

                    $output->writeln('"Values present in form: "');

                    foreach ($field['values'] as $fv) {
                        if ($field['type'] == 'rating') {
                            $output->writeln('"' . $fv['value'] . '"');
                        } else {
                            $output->writeln('"' . $fv['label'] . '"');
                        }
                    }

                    $output->writeln('""');
                    $output->writeln('"Potential invalid values: "');

                    $idsStr = [];

                    foreach ($values as $v) {
                        $idsStr[$v['value']][] = $v['id'];
//                        $idsStr .= $v['id'].',';
//                        $output->writeln('     ['.$v['id'].'] '.$v['value']);
                    }

                    foreach ($idsStr as $value => $ids) {
                        $output->writeln('"' . $value . '","[enter correct value here]","' . implode(',', $ids) . '"');
                    }


                    $invalidValues[$formId . ' - ' . $formName][$fieldName] = $values;
                    $wrong = true;
                }
            }

            if (!$wrong) {
                $output->writeln('""');
                $output->writeln('"Looks good!"');
            }
            $output->writeln('"---------------------------------------"');
        }

        foreach ($invalidValues as $idx => $formId) {
            if (!count($formId)) {
                unset($invalidValues[$idx]);
            }
        }

//        echo '<pre>'.print_r($invalidValues,true).'</pre>';
    }
}
