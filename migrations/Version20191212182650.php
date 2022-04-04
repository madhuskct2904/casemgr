<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191212182650 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql("UPDATE modules SET `group` = 'multiple' WHERE `key` = 'participants_contact'");
    }

    public function down(Schema $schema) : void
    {
        $this->addSql("UPDATE modules SET `group` = 'core' WHERE `key` = 'participants_contact'");
    }
}
