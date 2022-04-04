<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210126111735 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE workspace_public_files (id INT AUTO_INCREMENT NOT NULL, account_id INT DEFAULT NULL, user_id INT DEFAULT NULL, original_filename VARCHAR(255) NOT NULL, server_filename VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, public_url VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, deleted_at DATETIME DEFAULT NULL, INDEX IDX_E46631A59B6B5FBA (account_id), INDEX IDX_E46631A5A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE workspace_public_files ADD CONSTRAINT FK_E46631A59B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE workspace_public_files ADD CONSTRAINT FK_E46631A5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE workspace_public_files');
    }
}
