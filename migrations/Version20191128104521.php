<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191128104521 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE accounts ADD parent_account INT DEFAULT NULL, ADD creating_children_accounts_enabled TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE accounts ADD CONSTRAINT FK_CAC89EACF7E22E2 FOREIGN KEY (parent_account) REFERENCES accounts (id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("ALTER TABLE accounts DROP parent_account, DROP creating_children_accounts_enabled");
//        $this->addSql('ALTER TABLE accounts DROP FOREIGN KEY FK_CAC89EACF7E22E2');
//        $this->addSql('DROP INDEX IDX_CAC89EACF7E22E2 ON accounts');
    }
}
