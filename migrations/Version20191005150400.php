<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191005150400 extends AbstractMigration
{

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE `messages` CHANGE `status` `status` VARCHAR(255) NULL');
        $this->addSql('ALTER TABLE `messages` CHANGE `to_phone` `to_phone` VARCHAR(255) NULL');
        $this->addSql('ALTER TABLE `messages` CHANGE `sid` `sid` VARCHAR(255) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `messages` CHANGE `status` `status` VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE `messages` CHANGE `to_phone` `to_phone` VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE `messages` CHANGE `sid` `sid` VARCHAR(255) NOT NULL');
    }
}
