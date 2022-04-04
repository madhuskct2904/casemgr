<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200124145614 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE messages ADD case_manager_secondary_id INT NULL');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE messages DROP COLUMN case_manager_secondary_id');

    }
}
