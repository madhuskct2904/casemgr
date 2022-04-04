<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Dompdf\Exception;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200212114406 extends AbstractMigration
{
    private $columnsMap = [];

    public function getDescription() : string
    {
        return '';
    }

    public function preUp(Schema $schema): void {
        $query = "SELECT id, columns_map FROM `modules` WHERE `role` = 'profile'";
        $data = $this->connection->prepare($query);
        $data->execute();

        foreach($data as $row) {
            $this->columnsMap[$row['id']] = json_decode($row['columns_map'], true);
        }
    }

    public function preDown(Schema $schema): void
    {
        $query = "SELECT id, columns_map FROM `modules` WHERE `role` = 'profile'";
        $data = $this->connection->prepare($query);
        $data->execute();

        foreach($data as $row) {
            $this->columnsMap[$row['id']] = json_decode($row['columns_map'], true);
        }
    }

    public function up(Schema $schema) : void
    {
        foreach($this->columnsMap as $moduleId => $map) {
            $map['programs'] = 'Programs';
            $map = addslashes(json_encode($map));
            $this->addSql("UPDATE modules SET columns_map = '" . $map."' WHERE id = $moduleId");
        }
    }

    public function down(Schema $schema) : void
    {
        foreach($this->columnsMap as $moduleId => $map) {

            if(!isset($map['programs'])) {
                continue;
            }

            unset($map['programs']);
            $map = addslashes(json_encode($map));
            $this->addSql("UPDATE modules SET columns_map = '" . $map."' WHERE id = $moduleId");
        }
    }
}
