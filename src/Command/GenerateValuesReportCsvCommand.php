<?php

namespace App\Command;

use App\Domain\Form\FormSchemaHelper;
use App\Enum\FormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateValuesReportCsvCommand extends Command
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
            ->setName('app:generate-values-report-csv')
            ->setDescription('Generate CSV with all correct/incorrect values for all forms');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $forms = $this->doctrine->getRepository('App:Forms')->findBy([], ['name'=>'ASC']);

        foreach ($forms as $form) {
            if ($form->getType() != FormType::FORM) {
                continue;
            }

            $formId = $form->getId();
            $formName = $form->getName();

            $output->writeln('"Form: '.$formName.'","","'.$formId.'"');
            $output->writeln('"Present in organizations: "');

            foreach ($form->getAccounts() as $organization) {
                $output->writeln('"'.$organization->getOrganizationName().'","","'.$organization->getId().'"');
            }

            $this->formSchemaHelper->setForm($form);
            $fields = $this->formSchemaHelper->getFlattenColumns();

            foreach ($fields as $field) {
                if (!isset($field['name'])) {
                    continue;
                }

                if (!isset($field['values'])) {
                    continue;
                }

                $fieldName = $field['name'];
                $fieldDescription = $field['description'];

                $output->writeln('""');
                $output->writeln('"Field:"');
                $output->writeln('"'.$fieldDescription.'","","'.$fieldName.'"');
                $output->writeln('""');
                $output->writeln('"Values: "');


                $connection = $this->doctrine->getManager()->getConnection();

                foreach ($field['values'] as $v) {
                    if (!isset($v['value'])) {
                        continue;
                    }

                    $value = $v['value'];
                    $label = $v['label'];

                    $dbValue = addslashes($value);
                    $dbLabel = addslashes($label);

                    $ids = $connection->fetchAllAssociative("SELECT distinct(fv.id) FROM forms_values fv JOIN forms_data fd ON fv.data_id = fd.id JOIN forms f ON fd.form_id = f.id
 WHERE f.id = $formId AND fv.name = '$fieldName' AND (fv.value = '$dbValue' OR fv.value = '$dbLabel')");

                    $ids = array_column($ids, 'id');

                    $value = str_replace('"', '""', $value);
                    $label = str_replace('"', '""', $label);
                    $output->writeln('"'.$value.'","'.$label.'","","'.implode(',', $ids).'"');
                }
            }

            $output->writeln('"---------------------------------------"');
        }
    }
}
