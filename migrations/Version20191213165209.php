<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Update table due to new changes - add "Program" type for accounts
 */
final class Version20191213165209 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql("UPDATE `accounts` SET account_type = 'child' WHERE parent_account IS NOT NULL");
        $this->addSql("UPDATE `accounts` SET account_type = 'parent' WHERE creating_children_accounts_enabled = 1");
        $this->addSql("UPDATE `accounts` SET account_type = 'default' WHERE creating_children_accounts_enabled = 0 and parent_account IS NULL");
        $this->addSql("ALTER TABLE `accounts` ADD hipaa_regulated TINYINT(1) DEFAULT 0");
        $this->addSql("ALTER TABLE `accounts` DROP `creating_children_accounts_enabled`");

    }

    public function down(Schema $schema) : void
    {
        $this->addSql("ALTER TABLE `accounts` ADD `creating_children_accounts_enabled`");
        $this->addSql("UPDATE `accounts` SET `creating_children_accounts_enabled` = 1 WHERE account_type = parent");
        $this->addSql("UPDATE `accounts` SET account_type = 'casemgr'");
        $this->addSql("ALTER TABLE `accounts` DROP `hipaa_regulated`");
    }
}
