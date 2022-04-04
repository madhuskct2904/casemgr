<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Update reports tables for improved reports
 */
final class Version20190923212428 extends AbstractMigration implements ContainerAwareInterface
{
    private $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE reports ADD `results_count` INT UNSIGNED DEFAULT NULL');

        $this->addSql('CREATE TABLE reports_forms (`id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL, `report_id` INT, `form_id` INT, `invalidated_at` DATETIME DEFAULT NULL)');

        $this->addSql('ALTER TABLE reports_forms ADD CONSTRAINT FK_FD3F1BF7BB44124B FOREIGN KEY (report_id) REFERENCES reports (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reports_forms ADD CONSTRAINT FK_FD3F1BF7A76ED395 FOREIGN KEY (form_id) REFERENCES forms (id) ON DELETE CASCADE');

    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE reports DROP results_count');

        $this->addSql('ALTER TABLE reports_forms DROP FOREIGN KEY FK_FD3F1BF7A76ED395');
        $this->addSql('ALTER TABLE reports_forms DROP FOREIGN KEY FK_FD3F1BF7BB44124B');

        $this->addSql('DROP TABLE reports_forms');

    }
}
