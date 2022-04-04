<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200323132454 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE referral (id INT AUTO_INCREMENT NOT NULL, data_id INT DEFAULT NULL, account_id INT DEFAULT NULL, last_action_user INT DEFAULT NULL, enrolled_participant_id INT DEFAULT NULL, status VARCHAR(63) NOT NULL, comment TEXT NOT NULL, created_at DATETIME NOT NULL, last_action_at DATETIME DEFAULT NULL, INDEX IDX_73079D0037F5A13C (data_id), INDEX IDX_73079D009B6B5FBA (account_id), INDEX IDX_73079D00BB44124B (last_action_user), INDEX IDX_73079D0074AB6359 (enrolled_participant_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE referral ADD CONSTRAINT FK_73079D0037F5A13C FOREIGN KEY (data_id) REFERENCES forms_data (id)');
        $this->addSql('ALTER TABLE referral ADD CONSTRAINT FK_73079D009B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id)');
        $this->addSql('ALTER TABLE referral ADD CONSTRAINT FK_73079D00BB44124B FOREIGN KEY (last_action_user) REFERENCES users (id)');
        $this->addSql('ALTER TABLE referral ADD CONSTRAINT FK_73079D0074AB6359 FOREIGN KEY (enrolled_participant_id) REFERENCES users (id)');

    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE referral');
    }
}
