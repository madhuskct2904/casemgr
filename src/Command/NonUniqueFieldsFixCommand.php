<?php

namespace App\Command;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class NonUniqueFieldsFixCommand extends Command
{
    protected ManagerRegistry $doctrine;

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->doctrine = $doctrine;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('app:non-unique-fields-fix')
            ->setDescription('Fix forms if there are non-unique identifiers for fields. Fixes also forms values.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $conn = $this->doctrine->getManager()->getConnection();

        $formSql = "SELECT id, data FROM forms";

        $formResult = $conn->fetchAllAssociative($formSql);

        $i = 0;

        foreach ($formResult as $form) {

            $formId = $form['id'];

            $modify = false;

            $formData = json_decode($form['data'], true);

            $fieldsCounts = [];
            $fieldsToModify = [];

            foreach ($formData as $fldIdx => $fieldData) {

                if (!isset($fieldData['type'], $fieldData['name'])) {
                    unset($formData[$fldIdx]);
                    $modify = true;
                    continue;
                }

                if ($fieldData['type'] == 'accordion') {

                    foreach ($fieldData['values'] as $accordionFldIdx => $accordionField) {

                        if (!isset($accordionField['name'], $accordionField['type'])) {
                            unset($formData[$fldIdx]['values'][$accordionFldIdx]);
                            $modify = true;
                            continue;
                        }

                        if (isset($fieldsCounts[$accordionField['name']])) {
                            $fieldsCounts[$accordionField['name']]++;
                            $newName = $accordionField['type'] . '-' . (number_format(microtime(true) * 1000, 0, '.', '') + ++$i);
                            $formData[$fldIdx]['values'][$accordionFldIdx]['name'] = $newName;
                            $fieldsToModify[] = [$formId, $accordionField['name'], $newName, $fieldsCounts[$accordionField['name']]];
                            $modify = true;
                        } else {
                            $fieldsCounts[$accordionField['name']] = 1;
                        }
                    }
                }

                if (isset($fieldsCounts[$fieldData['name']])) {
                    $fieldsCounts[$fieldData['name']]++;
                    $newName = $fieldData['type'] . '-' . (number_format(microtime(true) * 1000, 0, '.', '') + ++$i);
                    $formData[$fldIdx]['name'] = $newName;
                    $fieldsToModify[] = [$formId, $fieldData['name'], $newName, $fieldsCounts[$fieldData['name']]];
                    $modify = true;
                } else {
                    $fieldsCounts[$fieldData['name']] = 1;
                }
            }

            if ($modify) {
                $updatedFormData = json_encode($formData);

                $sql = "UPDATE forms SET `data` = ? WHERE id = $formId";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(1, $updatedFormData);
                $stmt->execute();

                $output->writeln('Updating form: ' . $formId);

                foreach ($fieldsToModify as $fieldToModify) {
                    $this->rename($fieldToModify[0], $fieldToModify[1], $fieldToModify[2], $fieldToModify[3], $output);
                }

                $output->writeln('----------------------------------------------------------------------------------------------------------------------');

            }

        }

    }

    private function rename(int $formId, string $fieldName, string $newFieldName, int $occurrence, $output)
    {
        $conn = $this->doctrine->getManager()->getConnection();

        $sql = "SELECT fv.id as fv_id, fv.name, fv.value, fv.data_id, fd.form_id FROM forms_values fv JOIN forms_data fd ON fd.id = fv.data_id WHERE name = '$fieldName' AND fd.form_id = $formId ORDER BY fv_id";

        $results = $conn->fetchAllAssociative($sql);
        $dataIds = [];

        foreach ($results as $row) {


            isset($dataIds[$row['data_id']]) ? $dataIds[$row['data_id']]++ : $dataIds[$row['data_id']] = 1;

            if ($dataIds[$row['data_id']] === $occurrence) {
                $fvId = $row['fv_id'];
                $dataId = $row['data_id'];
                $sql = "UPDATE forms_values SET name = '$newFieldName' WHERE id = $fvId;";
                $conn->exec($sql);
                $output->writeln("Value ID: [$fvId]: Data ID: [$dataId]: Renamed [$fieldName] to [$newFieldName] for [$occurrence] occurrence.");
            }

        }

    }

}
