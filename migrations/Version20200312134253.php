<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200312134253 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {

        $columnsMap = addslashes(json_encode([
            'organization_id' => 'Organization ID',
            'first_name'      => 'First Name',
            'last_name'       => 'Last Name',
            'date_birth'      => 'Date of Birth',
            'phone_number'    => 'Phone Number',
            'email'           => 'Email Address (Referral Contact, Status Notification)',
            'date_completed'  => 'Date Completed'
        ]));

        $this->addSql("INSERT INTO modules (`group`, `key`, `name`, `columns_map`,`role`) VALUES ('multiple', 'individuals_referral', 'Individual Referral', '" . $columnsMap . "','referral')");

        $columnsMap = addslashes(json_encode([
            'organization_id' => 'Organization ID',
            'name'            => 'Entity Name',
            'phone_number'    => 'Phone Number',
            'email'           => 'E-mail Address',
            'date_completed'  => 'Date Completed'
        ]));


        $this->addSql("INSERT INTO modules (`group`, `key`, `name`, `columns_map`,`role`) VALUES ('multiple', 'members_referral', 'Member Referral', '" . $columnsMap . "','referral')");

    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM modules WHERE `key` IN ('individuals_referral','members_referral')");

        // this down() migration is auto-generated, please modify it to your needs

    }
}
