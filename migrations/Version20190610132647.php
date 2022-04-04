<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190610132647 extends AbstractMigration implements ContainerAwareInterface
{
    private $container;

    public function getDescription() : string
    {
        return '';
    }

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }


    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE forms_data ADD account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE forms_data ADD CONSTRAINT FK_3FFE2F69B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id)');
        $this->addSql('CREATE INDEX IDX_3FFE2F69B6B5FBA ON forms_data (account_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP INDEX IDX_3FFE2F69B6B5FBA ON forms_data');
        $this->addSql('ALTER TABLE forms_data DROP account_id');
        $this->addSql('ALTER TABLE reports CHANGE status status VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE type type VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci');
    }

    public function postUp(Schema $schema): void
    {
        $query = "SELECT f.id AS form_id, fa.accounts_id AS account_id FROM forms f LEFT JOIN forms_accounts fa ON f.id = fa.forms_id";
        $data = $this->connection->prepare($query);
        $data->execute();

        foreach($data as $row) {
            $formId = $row['form_id'];
            $accountId = $row['account_id'];
            if($accountId) {
                $query2 = "UPDATE forms_data SET account_id = $accountId WHERE form_id = $formId";
                $this->connection->executeQuery($query2);
            }
        }
    }
}
