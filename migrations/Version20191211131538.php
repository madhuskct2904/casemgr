<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Dompdf\Exception;

/**
 * Add label to recent conditions
 */
final class Version20191211131538 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $query = "SELECT `id`, `data` FROM `reports` WHERE `data` LIKE '%recent%'";
        $reports = $this->connection->prepare($query);
        $reports->execute();

        foreach ($reports as $row) {
            $data = json_decode($row['data'], true);
            $updateReport = false;

            foreach ($data as $formIdx => $formData) {
                $formId = $formData['form_id'];

                foreach ($formData['fields'] as $fieldIdx => $field) {
                    if (!count($field['conditions'])) {
                        continue;
                    }

                    foreach ($field['conditions'] as $conditionIdx => $cond) {
                        if (!isset($cond['type']) || $cond['type'] !== 'recent') {
                            continue;
                        }

                        $q2 = "SELECT `id`,`data` FROM `forms` WHERE ID = $formId";
                        $forms = $this->connection->prepare($q2);
                        $forms->execute();
                        $res = $forms->fetchAllAssociative();

                        $updateReport = true;

                        if (!count($res)) {
                            $data[$formIdx]['fields'][$fieldIdx]['conditions'][$conditionIdx]['label'] = '';
                        }

                        $formData = json_decode($res[0]['data'], true);

                        foreach ($formData as $formFieldIdx => $formFieldData) {
                            if ($formFieldData['name'] == $cond['value']) {
                                $data[$formIdx]['fields'][$fieldIdx]['conditions'][$conditionIdx]['label'] = $formFieldData['description'];
                                continue 2;
                            }
                        }
                    }

                }
            }

            if ($updateReport) {
                $reportId = $row['id'];
                $data = json_encode($data);
                $this->addSql("UPDATE reports SET data = '" . $data . "' WHERE id=" . $reportId);
                $this->addSql("UPDATE reports_forms SET invalidated_at = NOW()");
            }
        }
    }

    public function down(Schema $schema): void
    {
        $query = "SELECT `id`, `data` FROM `reports` WHERE `data` LIKE '%recent%'";
        $reports = $this->connection->prepare($query);
        $reports->execute();

        foreach ($reports as $row) {
            $data = json_decode($row['data'], true);
            $updateReport = false;

            foreach ($data as $formIdx => $formData) {

                foreach ($formData['fields'] as $fieldIdx => $field) {
                    if (!count($field['conditions'])) {
                        continue;
                    }

                    foreach ($field['conditions'] as $conditionIdx => $cond) {
                        if (!isset($cond['type']) || $cond['type'] !== 'recent') {
                            continue;
                        }

                        unset($data[$formIdx]['fields'][$fieldIdx]['conditions'][$conditionIdx]['label']);
                    }
                }
            }

            if ($updateReport) {
                $reportId = $row['id'];
                $this->addSql("UPDATE reports SET data = '" . $data . "' WHERE id=" . $reportId);
                $this->addSql("UPDATE reports_forms SET invalidated_at = NOW()");
            }
        }
    }
}
