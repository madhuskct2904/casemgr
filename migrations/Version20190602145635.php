<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190602145635 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('INSERT INTO modules (`group`, `key`, `name`) VALUES ("organization_general","organization_general","Organization: General")');
        $this->addSql('INSERT INTO modules (`group`, `key`, `name`) VALUES ("organization_organization","organization_organization","Organization: Organization")');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('DELETE FROM `modules` WHERE `key` IN ("organization_general","organization_organization")');
        $this->addSql('ALTER TABLE `modules` auto_increment = 6');
    }
}
