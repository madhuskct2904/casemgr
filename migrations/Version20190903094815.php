<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Enum\ParticipantStatus;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190903094815 extends AbstractMigration implements ContainerAwareInterface
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

        $this->addSql('ALTER TABLE users_data CHANGE status status_label VARCHAR(100)');
        $this->addSql('ALTER TABLE users_data ADD status SMALLINT DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE users_data DROP COLUMN status');
        $this->addSql('ALTER TABLE users_data CHANGE status_label status VARCHAR(45)');
    }

    public function postUp(Schema $schema): void
    {
        parent::postUp($schema);
        $em = $this->container->get('doctrine.orm.entity_manager');
        $usersData = $em->getRepository('App:UsersData')->findAll();

        foreach($usersData as $userData) {

            $label = $userData->getStatusLabel();

            if($label) {
                $label = strtolower($label);
                if($label == 'dismissed') {
                    $userData->setStatus(ParticipantStatus::DISMISSED);
                } else {
                    $userData->setStatus(ParticipantStatus::ACTIVE);
                }
            }
        }

        $em->flush();
    }
}
