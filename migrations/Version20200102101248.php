<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200102101248 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('CREATE TABLE participant_directory_columns (`account_id` INT, `columns` LONGTEXT)');
        $this->addSql('ALTER TABLE participant_directory_columns ADD CONSTRAINT FK_PDC_ACCOUNT_ID FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participant_directory_columns ADD UNIQUE (account_id)');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql("DROP TABLE `participant_directory_columns`");
    }
}
