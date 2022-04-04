<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210115113041 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('TRUNCATE TABLE imports');
        $this->addSql('ALTER TABLE imports ADD context VARCHAR(31) NOT NULL, ADD map LONGTEXT NOT NULL, ADD csv_header LONGTEXT NOT NULL, ADD success_rows LONGTEXT NOT NULL, ADD failed_rows LONGTEXT NOT NULL, ADD ignore_rows LONGTEXT NOT NULL, ADD total_rows INT DEFAULT NULL, ADD last_processed_row INT DEFAULT NULL, DROP results, DROP data, CHANGE original_file original_filename VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE imports ADD results LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, ADD data LONGTEXT CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, DROP context, DROP map, DROP csv_header, DROP success_rows, DROP failed_rows, DROP ignore_rows, DROP total_rows, DROP last_processed_row, CHANGE original_filename original_file VARCHAR(255) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`');
    }
}
