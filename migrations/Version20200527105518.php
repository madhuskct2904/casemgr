<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200527105518 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE reports_charts (id INT AUTO_INCREMENT NOT NULL, summary_id INT NOT NULL, title VARCHAR(255) NOT NULL, type VARCHAR(32) NOT NULL, labels VARCHAR(128) NOT NULL, yAxisLabel VARCHAR(255) DEFAULT NULL, xAxisLabel VARCHAR(255) DEFAULT NULL, dataSeries LONGTEXT NOT NULL, UNIQUE INDEX UNIQ_CF4A2D532AC2D45C (summary_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reports_charts ADD CONSTRAINT FK_CF4A2D532AC2D45C FOREIGN KEY (summary_id) REFERENCES report_summary (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE reports_charts');
    }
}
