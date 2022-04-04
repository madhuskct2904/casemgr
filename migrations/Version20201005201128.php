<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201005201128 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE shared_form (id INT AUTO_INCREMENT NOT NULL, account_id INT DEFAULT NULL, form_data_id INT DEFAULT NULL, participant_user_id INT DEFAULT NULL, user_id INT DEFAULT NULL, sent_at DATETIME DEFAULT NULL, sent_via VARCHAR(31) DEFAULT NULL, completed_at DATETIME DEFAULT NULL, status VARCHAR(31) NOT NULL, uid VARCHAR(63) NOT NULL, UNIQUE INDEX UNIQ_E87E96D7539B0606 (uid), INDEX IDX_E87E96D79B6B5FBA (account_id), UNIQUE INDEX UNIQ_E87E96D7699C107 (form_data_id), INDEX IDX_E87E96D73D631C9D (participant_user_id), INDEX IDX_E87E96D7A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE shared_form ADD CONSTRAINT FK_E87E96D79B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shared_form ADD CONSTRAINT FK_E87E96D7699C107 FOREIGN KEY (form_data_id) REFERENCES forms_data (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shared_form ADD CONSTRAINT FK_E87E96D73D631C9D FOREIGN KEY (participant_user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shared_form ADD CONSTRAINT FK_E87E96D7A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forms ADD share_with_participant TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE shared_form');
        $this->addSql('ALTER TABLE forms DROP share_with_participant');
    }
}
