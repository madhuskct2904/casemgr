<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191108113720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE members_data (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, system_id VARCHAR(255) DEFAULT NULL, case_manager VARCHAR(255) DEFAULT NULL, status SMALLINT DEFAULT NULL, status_label VARCHAR(100) DEFAULT NULL, phone_number VARCHAR(255) DEFAULT NULL, name VARCHAR(100) DEFAULT NULL, avatar VARCHAR(255) DEFAULT NULL, job_title VARCHAR(255) DEFAULT NULL, time_zone VARCHAR(255) DEFAULT NULL, date_completed VARCHAR(255) DEFAULT NULL, organization_id VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_45A0D2FFA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');

        $columnsMap = addslashes(json_encode([
            'organization_id' => 'Organization ID',
            'name'            => 'Entity Name',
            'phone_number'    => 'Phone Number',
            'email'           => 'E-mail Address',
            'system_id'       => 'System ID',
            'date_completed'  => 'Date Completed'
        ]));

        $this->addSql('ALTER TABLE accounts ADD participant_type SMALLINT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE users ADD user_data_type SMALLINT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE modules CHANGE `group` `group` VARCHAR(64) NOT NULL, CHANGE `key` `key` VARCHAR(64) NOT NULL, CHANGE name name VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE modules ADD `role` VARCHAR(64) NOT NULL');

        $this->addSql("UPDATE modules SET `group` = 'core' WHERE `group` = 'participants'");
        $this->addSql("UPDATE modules SET `group` = 'multiple' WHERE `group` = 'activities_services' OR `group` = 'assessment_outcomes'");
        $this->addSql("UPDATE modules SET `group` = 'organization' WHERE `group` = 'organization_general' OR `group` = 'organization_organization'");

        $this->addSql("UPDATE modules SET `role` = 'profile' WHERE `key` = 'participants_profile'");
        $this->addSql("UPDATE modules SET `role` = 'contact' WHERE `key` = 'participants_contact'");
        $this->addSql("UPDATE modules SET `role` = 'assignment' WHERE `key` = 'participants_assignment'");
        $this->addSql("UPDATE modules SET `role` = 'activities_services' WHERE `key` = 'activities_services'");
        $this->addSql("UPDATE modules SET `role` = 'assessment_outcomes' WHERE `key` = 'assessment_outcomes'");
        $this->addSql("UPDATE modules SET `role` = 'organization_general' WHERE `key` = 'organization_general'");
        $this->addSql("UPDATE modules SET `role` = 'organization_organization' WHERE `key` = 'organization_organization'");

        $this->addSql("INSERT INTO modules (`group`, `key`, `name`, `columns_map`,`role`) VALUES ('core', 'members_profile', 'Entity Profile', '" . $columnsMap . "','profile')");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql("DELETE FROM modules WHERE `key` = 'members_profile'");

        $this->addSql("UPDATE modules SET `group` = 'activities_services' WHERE `key` = 'activities_services'");
        $this->addSql("UPDATE modules SET `group` = 'assessment_outcomes' WHERE `key` = 'assessment_outcomes'");
        $this->addSql("UPDATE modules SET `group` = 'organization_general' WHERE `key` = 'organization_general'");
        $this->addSql("UPDATE modules SET `group` = 'organization_organization' WHERE `key` = 'organization_organization'");
        $this->addSql("UPDATE modules SET `group` = 'participants' WHERE `key` = 'participants_profile' OR `key` = 'participants_contact' OR `key` = 'participants_assignment'");

        $this->addSql('DROP TABLE members');
        $this->addSql('ALTER TABLE users DROP `user_data_type`');
        $this->addSql("ALTER TABLE accounts DROP participant_type");
        $this->addSql('ALTER TABLE modules CHANGE `group` `group` VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE `key` `key` VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE name name VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE modules DROP `role`');

    }
}
