<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20200910114345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE tutorial (id INT AUTO_INCREMENT NOT NULL, category_id INT DEFAULT NULL, header VARCHAR(255) NOT NULL, subheader VARCHAR(255) NOT NULL, thumbFile VARCHAR(255) DEFAULT NULL, videoFile VARCHAR(255) NOT NULL, createdAt DATETIME NOT NULL, videoSize BIGINT NOT NULL, INDEX IDX_C66BFFE912469DE2 (category_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tutorial_category (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, sort INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE tutorial ADD CONSTRAINT FK_C66BFFE912469DE2 FOREIGN KEY (category_id) REFERENCES tutorial_category (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE tutorial DROP FOREIGN KEY FK_C66BFFE912469DE2');
        $this->addSql('DROP TABLE tutorial');
        $this->addSql('DROP TABLE tutorial_category');
    }
}
