<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200211160257 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE programs (id INT AUTO_INCREMENT NOT NULL, account_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, status SMALLINT NOT NULL DEFAULT 0, creation_date DATETIME NOT NULL, INDEX IDX_F14965459B6B5FBA (account_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE programs_forms (programs_id INT NOT NULL, forms_id INT NOT NULL, INDEX IDX_2B5A9AD979AEC3C (programs_id), INDEX IDX_2B5A9AD9C99A463F (forms_id), PRIMARY KEY(programs_id, forms_id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE programs ADD CONSTRAINT FK_F14965459B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id)');
        $this->addSql('ALTER TABLE programs_forms ADD CONSTRAINT FK_2B5A9AD979AEC3C FOREIGN KEY (programs_id) REFERENCES programs (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE programs_forms ADD CONSTRAINT FK_2B5A9AD9C99A463F FOREIGN KEY (forms_id) REFERENCES forms (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE programs_forms DROP FOREIGN KEY FK_2B5A9AD979AEC3C');
        $this->addSql('DROP TABLE programs');
        $this->addSql('DROP TABLE programs_forms');
    }
}
