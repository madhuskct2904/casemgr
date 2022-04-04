<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200124165129 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('UPDATE modules SET columns_map = \'{"program_status_start_date":"Program Status Start Date","program_status_end_date":"Program Status End Date","primary_case_manager_id":"Primary Case Manager","secondary_case_manager_id":"Secondary Case Manager"}\' WHERE `key` = \'participants_assignment\'');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
