<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191207195343 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO `general_settings` (`key`, `value`) VALUES ('maintenance_message', 'Sorry for the inconvenience but we`re performing some maintenance at the moment.\n\nWe`ll be back online shortly!')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM general_settings WHERE key = 'maintenance_message'");
    }
}
