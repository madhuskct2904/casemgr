<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200124124140 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE account_clone (id INT AUTO_INCREMENT NOT NULL, source_account_id INT DEFAULT NULL, cloned_account_id INT DEFAULT NULL, status LONGTEXT NOT NULL, participants_map LONGTEXT NOT NULL, assignments_map LONGTEXT NOT NULL, forms_map LONGTEXT NOT NULL, UNIQUE INDEX UNIQ_DAE234A9E7DF2E9E (source_account_id), UNIQUE INDEX UNIQ_DAE234A98D2CDFBB (cloned_account_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE account_clone ADD CONSTRAINT FK_DAE234A9E7DF2E9E FOREIGN KEY (source_account_id) REFERENCES accounts (id)');
        $this->addSql('ALTER TABLE account_clone ADD CONSTRAINT FK_DAE234A98D2CDFBB FOREIGN KEY (cloned_account_id) REFERENCES accounts (id)');
        $this->addSql('ALTER TABLE account_merge CHANGE status status LONGTEXT NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE account_clone');
        $this->addSql('ALTER TABLE account_merge CHANGE status status INT NOT NULL');
    }
}
