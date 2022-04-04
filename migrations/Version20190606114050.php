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
final class Version20190606114050 extends AbstractMigration implements ContainerAwareInterface
{

    private $customSQL;
    private $container;

    public function getDescription(): string
    {
        return '';
    }

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function preUp(Schema $schema): void
    {
        $query = "SELECT id as form_id, account_id as account_id FROM `forms` WHERE account_id IS NOT NULL";
        $data = $this->connection->prepare($query);
        $data->execute();

        foreach($data as $row) {
            $formId = $row['form_id'];
            $accountId = $row['account_id'];
            $this->customSQL[] = "($formId, $accountId)";
        }

    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE forms_accounts (forms_id INT NOT NULL, accounts_id INT NOT NULL, INDEX IDX_C069879EC99A463F (forms_id), INDEX IDX_C069879ECC5E8CE8 (accounts_id), PRIMARY KEY(forms_id, accounts_id)) DEFAULT CHARACTER SET UTF8 COLLATE UTF8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE forms_accounts ADD CONSTRAINT FK_C069879EC99A463F FOREIGN KEY (forms_id) REFERENCES forms (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forms_accounts ADD CONSTRAINT FK_C069879ECC5E8CE8 FOREIGN KEY (accounts_id) REFERENCES accounts (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE forms DROP FOREIGN KEY FK_FD3F1BF79B6B5FBA');
        $this->addSql('DROP INDEX IDX_FD3F1BF79B6B5FBA ON forms');
        $this->addSql('ALTER TABLE forms DROP account_id');
    }

    public function postUp(Schema $schema): void {
        $sql = 'INSERT INTO forms_accounts (forms_id, accounts_id) VALUES ' . implode(',',$this->customSQL);
        $this->connection->executeQuery($sql);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE forms_accounts');
        $this->addSql('ALTER TABLE forms ADD account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE forms ADD CONSTRAINT FK_FD3F1BF79B6B5FBA FOREIGN KEY (account_id) REFERENCES accounts (id)');
        $this->addSql('CREATE INDEX IDX_FD3F1BF79B6B5FBA ON forms (account_id)');
    }
}
