<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191231135604 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE `forms` ADD custom_columns TEXT NULL");

    }

    public function down(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE `forms` DROP `custom_columns`");

    }
}
