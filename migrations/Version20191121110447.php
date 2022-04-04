<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20191121110447 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $connection = $this->connection;
        $reports = $connection->fetchAllAssociative('SELECT * FROM reports');
        $forms = $connection->fetchAllAssociative('SELECT `group`, forms.id as form_id FROM `forms` JOIN `modules` ON forms.module_id = modules.id');
        $coreForms = [];

        foreach($forms as $item) {
            if($item['group'] == 'core') {
               $coreForms[] = $item['form_id'];
            }
        }

        foreach ($reports as $report) {

                $data = json_decode($report['data'], true);

                foreach ($data as $formIdx => $form) {

                    $isNull = true;

                    foreach ($form['fields'] as $fieldIdx => $field) {
                        $newConditions = $field['conditions'];

                        if (isset($field['conditions'])) {

                            foreach ($field['conditions'] as $idx => $cond) {

                                if (isset($cond['isNull'])) {

                                    unset($newConditions[$idx]);

                                    if(!$cond['isNull']) {
                                        $isNull = false;
                                    }
                                }
                            }
                        }

                        if(!in_array($form['form_id'], $coreForms)) {
                            $data[$formIdx]['isNull'] = $isNull;
                        };

                        $data[$formIdx]['fields'][$fieldIdx]['conditions'] = array_values($newConditions);
                    }
                }

                $reportId = $report['id'];
                $data = addslashes(json_encode($data));

                $this->addSql("UPDATE reports SET data = '".$data."' WHERE id=".$reportId);
                $this->addSql("UPDATE reports_forms SET invalidated_at = NOW()");
        }
    }

    public function down(Schema $schema) : void
    {
        $connection = $this->connection;
        $reports = $connection->fetchAllAssociative('SELECT * FROM reports');

        foreach ($reports as $report) {

            $data = json_decode($report['data'], true);

            foreach ($data as $formIdx => $form) {

                $isNull = $form['isNull'];

                foreach ($form['fields'] as $fieldIdx => $field) {
                    $newConditions = $field['conditions'];
                    $newConditions[] = ['isNull' => $isNull];
                    $data[$formIdx]['fields'][$fieldIdx]['conditions'] = array_values($newConditions);
                }

                unset($data[$formIdx]['isNull']);
            }

            $reportId = $report['id'];
            $data = addslashes(json_encode($data));

            $this->addSql("UPDATE reports SET data = '".$data."' WHERE id=".$reportId);
            $this->addSql("UPDATE reports_forms SET invalidated_at = NOW()");

        }

    }
}
