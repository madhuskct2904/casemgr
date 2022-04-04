<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200221105525 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE emails_recipients (id INT AUTO_INCREMENT NOT NULL, email_message_id INT DEFAULT NULL, user_id INT DEFAULT NULL, email VARCHAR(255) NOT NULL, name VARCHAR(255) DEFAULT NULL, sent_at DATETIME DEFAULT NULL, last_action_date DATETIME DEFAULT NULL, status INT NOT NULL, INDEX IDX_7CD62872FFC9E1F6 (email_message_id), INDEX IDX_7CD62872A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE email_message (id INT AUTO_INCREMENT NOT NULL, creator_id INT DEFAULT NULL, template_id INT DEFAULT NULL, subject VARCHAR(255) NOT NULL, header VARCHAR(100) NOT NULL, body LONGTEXT NOT NULL, sender VARCHAR(255) NOT NULL, recipients_group VARCHAR(100) DEFAULT NULL, recipients_option VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, status INT NOT NULL, INDEX IDX_B7D58B061220EA6 (creator_id), INDEX IDX_B7D58B05DA0FB8 (template_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE email_template (id INT AUTO_INCREMENT NOT NULL, creator_id INT DEFAULT NULL, modified_by_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, header VARCHAR(100) NOT NULL, body LONGTEXT NOT NULL, sender VARCHAR(100) NOT NULL, created_at DATETIME NOT NULL, modified_at DATETIME DEFAULT NULL, INDEX IDX_9C0600CA61220EA6 (creator_id), INDEX IDX_9C0600CA99049ECE (modified_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE emails_recipients ADD CONSTRAINT FK_7CD62872FFC9E1F6 FOREIGN KEY (email_message_id) REFERENCES email_message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE emails_recipients ADD CONSTRAINT FK_7CD62872A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_message ADD CONSTRAINT FK_B7D58B061220EA6 FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_message ADD CONSTRAINT FK_B7D58B05DA0FB8 FOREIGN KEY (template_id) REFERENCES email_template (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_template ADD CONSTRAINT FK_9C0600CA61220EA6 FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE email_template ADD CONSTRAINT FK_9C0600CA99049ECE FOREIGN KEY (modified_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE emails_recipients DROP FOREIGN KEY FK_7CD62872FFC9E1F6');
        $this->addSql('ALTER TABLE email_message DROP FOREIGN KEY FK_B7D58B05DA0FB8');
        $this->addSql('DROP TABLE emails_recipients');
        $this->addSql('DROP TABLE email_message');
        $this->addSql('DROP TABLE email_template');
    }
}
