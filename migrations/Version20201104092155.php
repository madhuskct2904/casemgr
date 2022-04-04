<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201104092155 extends AbstractMigration
{
    private $statusMap;

    public function getDescription(): string
    {
        return '';
    }

    public function preUp(Schema $schema): void
    {
        $query = "SELECT id, status, publish FROM forms";
        $data = $this->connection->prepare($query);
        $data->execute();

        foreach ($data as $row) {
            $this->statusMap[$row['id']] = ['status' => (int)$row['status'], 'publish' => (int)$row['publish']];
        }
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE forms CHANGE status status TINYINT(1) NOT NULL, CHANGE publish publish TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE forms ADD CHANGE status status VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE publish publish VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
    }

    public function postUp(Schema $schema): void
    {
        foreach ($this->statusMap as $id => $statuses) {
            $status = $statuses['status'];
            $publish = $statuses['publish'];
            $query = "UPDATE forms SET status = $status, publish = $publish WHERE id = $id";
            $this->connection->exec($query);
        }
    }
}
