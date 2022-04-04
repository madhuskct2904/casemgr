<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

final class Version20200715114414 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE participant_directory_columns DROP INDEX account_id, ADD INDEX IDX_6BC988FF9B6B5FBA (account_id)');
        $this->addSql('ALTER TABLE participant_directory_columns DROP FOREIGN KEY FK_PDC_ACCOUNT_ID');
        $this->addSql('ALTER TABLE participant_directory_columns ADD id INT AUTO_INCREMENT NOT NULL, ADD context VARCHAR(255) DEFAULT NULL, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE participant_directory_columns ADD CONSTRAINT FK_6BC988FF9B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE participant_directory_columns DROP INDEX IDX_6BC988FF9B6B5FBA, ADD UNIQUE INDEX account_id (account_id)');
        $this->addSql('ALTER TABLE participant_directory_columns MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE participant_directory_columns DROP FOREIGN KEY FK_6BC988FF9B6B5FBA');
        $this->addSql('ALTER TABLE participant_directory_columns DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE participant_directory_columns DROP id, DROP context');
        $this->addSql('ALTER TABLE participant_directory_columns ADD CONSTRAINT FK_PDC_ACCOUNT_ID FOREIGN KEY (account_id) REFERENCES accounts (id) ON DELETE CASCADE');
    }

    public function postUp(Schema $schema): void
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $query = $em->createQuery("UPDATE App\Entity\ParticipantDirectoryColumns c SET c.context = 'participant_directory'");
        $query->execute();
    }
}
