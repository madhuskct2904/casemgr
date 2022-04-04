<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191005104552 extends AbstractMigration
{

    /**
     * @param Schema $schema
     *
     * @throws DBALException
     */
    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE messages ADD `mass_message_id` INT UNSIGNED DEFAULT NULL');
        $this->addSql('ALTER TABLE messages ADD `error` varchar(255) DEFAULT NULL');

        $this->addSql('CREATE TABLE mass_messages (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL, `user_id` INT, `body` TEXT, `created_at` DATETIME DEFAULT NULL)');

        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_MASS_MESSAGE_ID FOREIGN KEY (mass_message_id) REFERENCES mass_messages (id) ON DELETE CASCADE');

    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_MASS_MESSAGE_ID');
        $this->addSql('ALTER TABLE messages DROP COLUMN mass_message_id');
        $this->addSql('ALTER TABLE messages DROP COLUMN error');

        $this->addSql('DROP TABLE mass_messages');
    }
}
