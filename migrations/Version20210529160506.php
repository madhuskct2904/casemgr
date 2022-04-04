<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210529160506 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE users_auth (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, account_id INT DEFAULT NULL, code VARCHAR(8) DEFAULT NULL, updated_at DATETIME DEFAULT NULL, browser_fingerprint VARCHAR(255) NOT NULL, email_sent TINYINT(1) NOT NULL DEFAULT 0, token VARCHAR(63) DEFAULT NULL, UNIQUE INDEX UNIQ_3757FE57A76ED395 (user_id), INDEX IDX_3757FE579B6B5FBA (account_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE `UTF8_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE users_auth ADD CONSTRAINT FK_3757FE57A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE users_auth ADD CONSTRAINT FK_3757FE579B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id)');


        $this->addSql('ALTER TABLE accounts ADD two_factor_auth_enabled TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE users_auth');
        $this->addSql('ALTER TABLE accounts DROP two_factor_auth_enabled');
    }
}
