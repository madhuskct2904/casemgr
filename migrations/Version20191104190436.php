<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191104190436 extends AbstractMigration
{
    /**
     * @return string
     */
    public function getDescription() : string
    {
        return '';
    }

    /**
     * @param Schema $schema
     */
    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE mass_messages ADD account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE mass_messages ADD CONSTRAINT FK_MASS_MESSAGES_ACCOUNT_ID FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE');

    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_MASS_MESSAGES_ACCOUNT_ID');
        $this->addSql('ALTER TABLE messages DROP COLUMN account_id');
    }
}
