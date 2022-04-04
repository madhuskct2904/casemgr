<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191217120240 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->updateForms();
        $this->removeProgramStatusFromDataMap();
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addProgramStatusToDataMap();
        $this->rollbackSystemConditionals();
    }

    private function updateForms()
    {
        $query = "SELECT `id`, `data`, `columns_map`, `system_conditionals` FROM `forms` WHERE `module_id` = 3";
        $forms = $this->connection->prepare($query);
        $forms->execute();

        foreach ($forms as $row) {

            $formId = $row['id'];
            $columnsMap = json_decode($row['columns_map'], true);
            $idx = array_search('program_status', array_column($columnsMap, 'name'));

            if (!$idx) {
                continue;
            }

            $programStatusField = $columnsMap[$idx]['value'];

            $fields = json_decode($row['data'], true);

            $fieldIdx = array_search($programStatusField, array_column($fields, 'name'));

            $programStatusValues = $fields[$fieldIdx]['values'];

            $systemConditionals = json_decode($row['system_conditionals'], true);

            $programStatusMap = [];

            if (is_array($systemConditionals)) {
                foreach ($systemConditionals as $conditional) {
                    if (isset($conditional['type']) && $conditional['type'] != 'PROGRAM_STATUS_CONDITION') {
                        continue;
                    }

                    foreach ($conditional['states'] as $state) {
                        $programStatusMap[$state['sourceProgramStatus']] = $conditional['setProgramStatus'] == 'active' ? 1 : 0;
                    }

                }
            }

            foreach ($programStatusValues as $programStatusIdx => $programStatusValue) {
                if (isset($programStatusMap[$programStatusValue['label']])) {
                    continue;
                }

                if (in_array(strtolower($programStatusValue['label']), ['pending', 'waitlist', 'active', 'follow-up'])) {
                    $programStatusMap[$programStatusValue['label']] = 1;
                } else {
                    $programStatusMap[$programStatusValue['label']] = 0;
                }
            }

            $newSystemConditionals = [
                'programStatus' => [
                    'field'      => $programStatusField,
                    'conditions' => $programStatusMap
                ]
            ];

            unset($columnsMap[$idx]);

            $columnsMap = json_encode($columnsMap);

            $newSystemConditionals = json_encode($newSystemConditionals);
            $this->addSql("UPDATE forms SET system_conditionals = '" . $newSystemConditionals . "' WHERE id=" . $formId);
            $this->addSql("UPDATE forms SET columns_map = '" . $columnsMap . "' WHERE id=" . $formId);

        }
    }

    private function removeProgramStatusFromDataMap()
    {
        $query = "SELECT `id`, `columns_map` FROM `modules` WHERE `role` = 'assignment'";
        $modules = $this->connection->prepare($query);
        $modules->execute();

        foreach ($modules as $moduleIdx => $moduleData) {
            $columnsMap = json_decode($moduleData['columns_map'], true);
            unset($columnsMap['program_status']);
            $columnsMap = json_encode($columnsMap);
            $this->addSql("UPDATE `modules` SET columns_map = '" . $columnsMap . "' WHERE id=" . $moduleData['id']);
        }
    }

    private function rollbackSystemConditionals()
    {
        $query = "SELECT `id`, `data`, `columns_map`, `system_conditionals` FROM `forms` WHERE `module_id` = 3";
        $forms = $this->connection->prepare($query);
        $forms->execute();

        foreach ($forms as $row) {

            $formId = $row['id'];

            $systemConditionals = json_decode($row['system_conditionals'], true);
            $fields = json_decode($row['data'], true);

            $states = [];

            foreach ($systemConditionals as $systemConditional) {
                if (!isset($systemConditional['programStatus'])) {
                    continue;
                }

                $fieldName = $systemConditional['field'];
                $idx = array_search($fieldName, array_column($fields, 'name'));


                foreach ($systemConditional['conditions'] as $label => $status) {
                    if (in_array($label, ['Pending', 'Waitlist', 'Active', 'Follow-Up', 'Dismissed'])) {
                        continue;
                    }


                    $states[] = [
                        'states'           => ['sourceProgramStatus' => $label],
                        'setProgramStatus' => $status ? 'active' : 'dismissed',
                        'name'             => $idx ? $fields[$idx]['description'] : $fieldName,
                        'type'             => 'PROGRAM_STATUS_CONDITION'
                    ];
                }
            }

            if (is_array($states)) {

                $states = json_encode($states);

                $columnsMap = json_decode($row['columns_map'], true);
                $columnsMap[] = ['name' => 'Program Status', 'value' => $fieldName];
                $columnsMap = json_encode($columnsMap);
                $this->addSql("UPDATE forms SET system_conditionals = '" . $states . "' WHERE id=" . $formId);
                $this->addSql("UPDATE forms SET columns_map = '" . $columnsMap . "' WHERE id=" . $formId);

            }
        }
    }

    private function addProgramStatusToDataMap()
    {
        $query = "SELECT `id`, `columns_map` FROM `modules` WHERE `role` = 'assignment'";
        $modules = $this->connection->prepare($query);
        $modules->execute();

        foreach ($modules as $moduleIdx => $moduleData) {
            $columnsMap = json_decode($moduleData['columns_map'], true);
            $columnsMap['program status'] = 'Program Status';
            $columnsMap = json_encode($columnsMap);
            $this->addSql("UPDATE `modules` SET columns_map = '" . $columnsMap . "' WHERE id=" . $moduleData['id']);
        }
    }

}
