<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191128175116 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql("CREATE TABLE `general_settings` (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL, `key` VARCHAR(32) NOT NULL, `value` TEXT NOT NULL)");
        $this->addSql("INSERT INTO `general_settings` (`key`, `value`) VALUES ('maintenance_mode', 'off')");
    }

    public function down(Schema $schema) : void
    {
        $this->addSql("DROP TABLE `general_settings`");
    }
}
