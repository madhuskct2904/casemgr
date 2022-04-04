<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ReportFolder;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191007092625 extends AbstractMigration implements ContainerAwareInterface
{
    private $container;

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }


    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE reports ADD report_folder_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_F11FA7453898D45A FOREIGN KEY (report_folder_id) REFERENCES reports_folders (id)');
        $this->addSql('CREATE INDEX IDX_F11FA7453898D45A ON reports (report_folder_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE reports DROP FOREIGN KEY FK_F11FA7453898D45A');
        $this->addSql('DROP INDEX IDX_F11FA7453898D45A ON reports');
        $this->addSql('ALTER TABLE reports DROP report_folder_id');
    }

    public function postUp(Schema $schema): void
    {
        $connection = $this->connection;
        $accounts = $connection->fetchAllAssociative('SELECT id FROM accounts');
        foreach ($accounts as $account) {
            $newFolderName = 'account' . $account['id'];
            $connection->executeQuery('INSERT INTO reports_folders (name) VALUES ("' . $newFolderName . '")');
            $insertId = $connection->lastInsertId();

            $connection->executeQuery('UPDATE reports_folders SET tree_root = '.$insertId.', lft = 1, lvl = 0, rgt = 2 WHERE id='.$insertId);

            if($insertId) {
                $connection->executeQuery('UPDATE reports SET report_folder_id=' . $insertId . ' WHERE account_id=' . $account['id']);
            }
        }
    }
}
