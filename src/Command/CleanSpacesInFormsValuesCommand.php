<?php

namespace App\Command;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CleanSpacesInFormsValuesCommand extends Command
{
    private $conn;
    private $proceed = false;
    private $updateFormMap = [];
    protected ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
        parent::__construct();
    }


    protected function configure()
    {
        $this
            ->setName('app:clean-spaces-in-forms-values')
            ->setDescription('Generate/run SQL for clean all double/unnecessary spaces in forms values.')
            ->addArgument('proceed', InputArgument::OPTIONAL, 'Proceed - run generated SQL');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->proceed = $input->getArgument('proceed') ? true : false;

        $this->conn = $this->doctrine->getManager()->getConnection();

        $sql = "SELECT `id`,`description`,`data` FROM forms;";

        $forms = $this->conn->fetchAllAssociative($sql);

        foreach ($forms as $form) {
            $this->updateFormMap[$form['id']] = false;
            $this->fixValues($form, $output);
        }
    }

    private function fixValues($form, $output)
    {
        $formFields = json_decode($form['data'], true);

        foreach ($formFields as $idx => $field) {
            if (isset($field['type']) && $field['type'] == 'accordion') {
                foreach ($field['values'] as $subIdx => $subField) {
                    if (isset($subField['values'])) {
                        $field['values'][$subIdx] = $this->fixField($subField, $form, $output);
                    }
                }
            } elseif (isset($field['values'])) {
                $field = $this->fixField($field, $form, $output);
            }

            $formFields[$idx] = $field;
        }

        $formData = json_encode($formFields);

        if ($this->updateFormMap[$form['id']]) {
            $formId = $form['id'];

            $formData = $this->conn->quote($formData);

            $sql = "UPDATE forms SET `data`=$formData WHERE id = $formId;";

            $output->writeln($sql);

            if ($this->proceed) {
                $this->conn->exec($sql);
            }

            $sql2 = "UPDATE reports_forms SET invalidated_at=NOW() WHERE form_id = $formId;";

            $output->writeln($sql2);

            if ($this->proceed) {
                $this->conn->exec($sql2);
            }

//            $output->writeln('Fixed form: '.$form['id'].' '.$form['description']);
        }
    }

    private function fixField($field, $form, $output)
    {
        $fname = $field['name'];
        $fid = $form['id'];

        foreach ($field['values'] as $idx => $fieldValue) {
            if (isset($fieldValue['value'])) {
                if (preg_match('/(?:\s\s+|\n|\t)/', $fieldValue['value'])) {
                    $output->writeln('Found double spaces in VALUE in field [' . $field['label'] . '] ' . $field['description'] . ':');

                    $oldValue = $fieldValue['value'];
                    $clear = preg_replace('/(?:\s\s+|\n|\t)/', ' ', $fieldValue['value']);

                    $field['values'][$idx]['value'] = $clear;

                    $oldValue = $this->conn->quote($oldValue);
                    $clear = $this->conn->quote($clear);

                    $sql ="update forms_values fv1, (select distinct(fv.id) from forms_values fv inner join forms_data fd on fv.data_id = fd.id inner join forms f on fd.form_id = f.id  where fv.value = $oldValue and f.id = $fid and fv.name LIKE '$fname%') t3 SET fv1.value = $clear where t3.id = fv1.id;";

                    $this->updateFormMap[$form['id']] = true;

                    $output->writeln($sql);

                    if ($this->proceed) {
                        $this->conn->exec($sql);
                    }

//                    $output->writeln('OLD: ' . $oldValue);
//                    $output->writeln('NEW: ' . $clear);
                }
            }

            if (isset($fieldValue['label'])) {
                if (preg_match('/(?:\s\s+|\n|\t)/', $fieldValue['label'])) {
//                    $output->writeln('/** Found double spaces in LABEL in field [' . $field['label'] . '] ' . $field['description'] . ':  */');

                    $oldValue = $fieldValue['label'];
                    $clear = preg_replace('/(?:\s\s+|\n|\t)/', ' ', $fieldValue['label']);

                    $field['values'][$idx]['label'] = $clear;

                    $oldValue = $this->conn->quote($oldValue);
                    $clear = $this->conn->quote($clear);

                    $sql = "update forms_values fv1, (select distinct(fv.id) from forms_values fv inner join forms_data fd on fv.data_id = fd.id inner join forms f on fd.form_id = f.id  where fv.value = $oldValue and f.id = $fid and  fv.name LIKE '$fname%') t3 SET fv1.value = $clear where t3.id = fv1.id;";

                    $this->updateFormMap[$form['id']] = true;

                    $output->writeln($sql);

                    if ($this->proceed) {
                        $this->conn->exec($sql);
                    }

//                    $output->writeln('OLD: ' . $oldValue);
//                    $output->writeln('NEW: ' . $clear);
                }
            }
        }

        return $field;
    }
}
