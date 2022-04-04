<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

final class Version20200807082558 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $reports = $this->connection->fetchAllAssociative("SELECT id, data FROM reports WHERE `data` LIKE '%date-%'");

        foreach ($reports as $report) {

            $data = json_decode($report['data'], true);
            $doChange = false;

            foreach ($data as $formIdx => $form) {

                foreach ($form['fields'] as $fieldIdx => $field) {

                    if (!isset($field['field'])) {
                        continue;
                    }

                    if (strpos($field['field'], 'date-') !== 0) {
                        continue;
                    }

                    if (!isset($field['conditions']) || !is_array($field['conditions']) || !count($field['conditions'])) {
                        continue;
                    }

                    foreach ($field['conditions'] as $fcondIdx => $fieldCondition) {
                        if (!in_array($fieldCondition['date'], ['lessthan', 'greaterthan', 'lessorequal', 'greaterorequal'])) {
                            continue;
                        }

                        if (!isset($fieldCondition['value'][0]) || !isset($fieldCondition['value'][1])) {
                            continue;
                        }

                        $phpDate = $fieldCondition['value'][1];
                        $data[$formIdx]['fields'][$fieldIdx]['conditions'][$fcondIdx]['value'] = $phpDate;
                        $doChange = true;
                    }
                }
            }

            if ($doChange) {
                $newData = json_encode($data);
                $stmt = $this->connection->prepare('UPDATE reports SET `data` = ? WHERE id = ?');
                $stmt->bindValue(1, $newData);
                $stmt->bindValue(2, $report['id']);
                $stmt->execute();
            }

        }

//        throw new \Exception('no i kutas');
    }

    public function down(Schema $schema): void
    {
        $reports = $this->connection->fetchAllAssociative("SELECT id, data FROM reports WHERE `data` LIKE '%date-%'");

        foreach ($reports as $report) {

            $data = json_decode($report['data'], true);
            $doChange = false;

            foreach ($data as $formIdx => $form) {

                foreach ($form['fields'] as $fieldIdx => $field) {

                    if (!isset($field['field'])) {
                        continue;
                    }

                    if (strpos($field['field'], 'date-') !== 0) {
                        continue;
                    }

                    if (!isset($field['conditions']) || !is_array($field['conditions']) || !count($field['conditions'])) {
                        continue;
                    }

                    foreach ($field['conditions'] as $fcondIdx => $fieldCondition) {
                        if (!in_array($fieldCondition['date'], ['lessthan', 'greaterthan', 'lessorequal', 'greaterorequal'])) {
                            continue;
                        }

                        if (!isset($fieldCondition['value'])) {
                            continue;
                        }

                        if (!is_string($fieldCondition['value'])) {
                            continue;
                        }

                        if (!isset($fieldCondition['dateFormat'])) {
                            continue;
                        }

                        $phpDate = $fieldCondition['value'];

                        $dateParts = explode('/', $phpDate);

                        $format = $fieldCondition['dateFormat'];
                        $formatParts = explode('/', $format);

                        $day = (int)$dateParts[array_search('DD', $formatParts)];
                        $month = (int)$dateParts[array_search('MM', $formatParts)];
                        $year = (int)$dateParts[array_search('YYYY', $formatParts)];

                        $jsDate = date(DATE_ATOM, mktime(0, 0, 0, $month, $day, $year));

                        $data[$formIdx]['fields'][$fieldIdx]['conditions'][$fcondIdx]['value'][0] = $jsDate;
                        $data[$formIdx]['fields'][$fieldIdx]['conditions'][$fcondIdx]['value'][1] = $phpDate;
                        $doChange = true;
                    }
                }
            }

            if ($doChange) {
                $newData = json_encode($data);
                $stmt = $this->connection->prepare('UPDATE reports SET `data` = ? WHERE id = ?');
                $stmt->bindValue(1, $newData);
                $stmt->bindValue(2, $report['id']);
                $stmt->execute();
            }

        }

        throw new \Exception('kutas');
    }

}
