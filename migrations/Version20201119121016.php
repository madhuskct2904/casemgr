<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201119121016 extends AbstractMigration
{
    private $recipientsOptionMap;

    public function getDescription(): string
    {
        return '';
    }

    public function preUp(Schema $schema): void
    {
        $emails = $this->connection->fetchAllAssociative("SELECT em.id, em.recipients_option, a.organization_name FROM email_message em JOIN accounts a WHERE recipients_group = 'users_by_account' AND a.id = em.recipients_option");

        foreach ($emails as $emailData) {
            $this->recipientsOptionMap[$emailData['id']] = json_encode([['id' => $emailData['recipients_option'], 'name' => $emailData['organization_name']]]);
        }
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_message MODIFY recipients_group TEXT NOT NULL');

        foreach ($this->recipientsOptionMap as $emailId => $recipientOption) {
            $this->addSql("UPDATE email_message SET recipients_option = '$recipientOption' WHERE id = $emailId");
        }

    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE email_message MODIFY recipients_group VARCHAR(100) DEFAULT NULL');
    }

}
