<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210518161633 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE forms ADD update_conditionals LONGTEXT NULL');
    }

    public function down(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE forms DROP update_conditionals');
    }
}
