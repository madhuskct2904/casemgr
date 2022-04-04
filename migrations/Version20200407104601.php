<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200407104601 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE users_activity_log (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, account_id INT DEFAULT NULL, event_name VARCHAR(255) NOT NULL, message VARCHAR(255) NOT NULL, details TEXT NOT NULL, date_time DATETIME NOT NULL, INDEX IDX_AE42D04BA76ED395 (user_id), INDEX IDX_AE42D04B9B6B5FBA (account_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE users_activity_log ADD CONSTRAINT FK_AE42D04BA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE users_activity_log ADD CONSTRAINT FK_AE42D04B9B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE users_activity_log');
    }
}
