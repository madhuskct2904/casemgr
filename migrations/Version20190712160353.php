<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190712160353 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE imports ADD form_account_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE imports ADD CONSTRAINT FK_7895ED1C92AC132E FOREIGN KEY (form_account_id) REFERENCES accounts (id)');
        $this->addSql('CREATE INDEX IDX_7895ED1C92AC132E ON imports (form_account_id)');
    }

    public function postUp(Schema $schema): void
    {
        parent::postUp($schema);

        $em = $this->container->get('doctrine.orm.entity_manager');
        $imports = $em->getRepository('App:Imports')->findAll();

        foreach($imports as $import)
        {
            if($form = $import->getForm()) {
                $formAccount = $form->getAccounts()->first();
                $import->setFormAccount($formAccount);
                $em->flush();
            }
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE imports DROP FOREIGN KEY FK_7895ED1C92AC132E');
        $this->addSql('DROP INDEX IDX_7895ED1C92AC132E ON imports');
        $this->addSql('ALTER TABLE imports DROP form_account_id');
    }
}
