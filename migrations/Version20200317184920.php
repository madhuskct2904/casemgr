<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200317184920 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $this->addSql('ALTER TABLE accounts_merge_form DROP FOREIGN KEY FK_4F82B979E7B95EBD');
        $this->addSql('ALTER TABLE accounts_merge_form DROP FOREIGN KEY FK_4F82B9796F6BCBFB');
        $this->addSql('ALTER TABLE accounts_merge_form DROP FOREIGN KEY FK_4F82B9799B6B5FBA');
        // this up() migration is auto-generated, please modify it to your needs
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE accounts_merge_form ADD CONSTRAINT FK_4F82B9799B6B5FBA FOREIGN KEY (account_id) REFERENCES account_merge (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE accounts_merge_form ADD CONSTRAINT FK_4F82B9796F6BCBFB FOREIGN KEY (parent_form) REFERENCES forms (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE accounts_merge_form ADD CONSTRAINT FK_4F82B979E7B95EBD FOREIGN KEY (child_form) REFERENCES forms (id) ON DELETE CASCADE');
    }
}
