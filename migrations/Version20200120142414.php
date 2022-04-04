<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200120142414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('UPDATE modules SET columns_map = \'{"program_status_start_date":"Program Status Start Date","program_status_end_date":"Program Status End Date","primary_case_manager_id":"Primary Case Manager","secondary_case_manager_id":"Secondary Case Manager"}\' WHERE `key` = \'participants_assigment\'');

        $connection = $this->connection;
        $forms = $connection->fetchAllAssociative('SELECT * FROM forms WHERE module_id = 3');


        foreach ($forms as $form) {
            $columnsMap = json_decode($form['columns_map'], true);

            if (!is_array($columnsMap)) {
                continue;
            }

            $columnsMap[] =
                [
                    'name'  => 'secondary_case_manager_id',
                    'value' => ''
                ];

            $formId = $form['id'];
            $columnsMap = addslashes(json_encode($columnsMap));

            $this->addSql("UPDATE forms SET columns_map = '" . $columnsMap . "' WHERE id=" . $formId);
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
