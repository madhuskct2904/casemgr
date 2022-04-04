<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20191230080539 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        $query = "SELECT `id`, `data`, `columns_map`, `system_conditionals` FROM `forms` WHERE `columns_map` != '[]' AND `columns_map` != ''";

        $forms = $this->connection->prepare($query);
        $forms->execute();

        foreach ($forms as $row) {

            $formId = $row['id'];
            $columnsMap = json_decode($row['columns_map'], true);

            if(!count($columnsMap)) {
                continue;
            }

            // remove keys

            $columnsMap = array_values($columnsMap);
            $newColumnsMap = json_encode($columnsMap);

            if($columnsMap == $newColumnsMap) {
                continue;
            }

            $this->addSql("UPDATE forms SET columns_map = '" . $newColumnsMap . "' WHERE id=" . $formId);
        }
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
