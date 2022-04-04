<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20201112130109 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE forms SET access_level = 2 WHERE module_id IN (4,5) AND access_level IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE forms SET access_level = NULL WHERE module_id IN (4,5) AND access_level IS NOT NULL");
    }
}
