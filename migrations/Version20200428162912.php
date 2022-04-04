<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200428162912 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('DROP TABLE accounts_merge_form');
        $this->addSql('DROP TABLE account_merge');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('CREATE TABLE accounts_merge_form (id INT AUTO_INCREMENT NOT NULL, account_id INT NOT NULL, parent_form INT NOT NULL, child_form INT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, fields_map LONGTEXT NOT NULL, use_conditions VARCHAR(100) NOT NULL, use_calculations VARCHAR(100) NOT NULL, columns_map VARCHAR(255) NOT NULL, use_columns_map VARCHAR(100) NOT NULL, use_system_conditions VARCHAR(100) NOT NULL, multiple_entries TINYINT(1) NOT NULL, module_key VARCHAR(255) NOT NULL, status VARCHAR(100) NOT NULL, INDEX IDX_4F82B9799B6B5FBA (account_id), INDEX UNIQ_4F82B9796F6BCBFB (parent_form), INDEX UNIQ_4F82B979E7B95EBD (child_form), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE account_merge (id INT AUTO_INCREMENT NOT NULL, parent_id INT DEFAULT NULL, child_id INT DEFAULT NULL, status INT NOT NULL, forms_map LONGTEXT NOT NULL, parent_forms_actions LONGTEXT NOT NULL, INDEX UNIQ_DDE64E28727ACA70 (parent_id), INDEX UNIQ_DDE64E28DD62C21B (child_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE account_merge ADD CONSTRAINT FK_DDE64E28727ACA70 FOREIGN KEY (parent_id) REFERENCES accounts (id)');
        $this->addSql('ALTER TABLE account_merge ADD CONSTRAINT FK_DDE64E28DD62C21B FOREIGN KEY (child_id) REFERENCES accounts (id)');
        $this->addSql('ALTER TABLE accounts_merge_form ADD new_form_id INT UNSIGNED DEFAULT NULL');
    }
}
