<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201116144623 extends AbstractMigration
{

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $result = $this->connection->fetchAllAssociative("SELECT * from credentials where widgets like '%Calendar%'");

        foreach ($result as $res) {

            $id = $res['id'];

            $widgets = json_decode($res['widgets'], true);

            foreach ($widgets['widgetsFirst'] as $widgetFirstIdx => $widgetFirst) {
                if ($widgetFirst['name'] == 'Calendar') {
                    unset($widgets['widgetsFirst'][$widgetFirstIdx]);
                }
            }

            foreach ($widgets['widgetsSecond'] as $widgetSecondIdx => $widgetSecond) {
                if ($widgetSecond['name'] == 'Calendar') {
                    unset($widgets['widgetsSecond'][$widgetSecondIdx]);
                }
            }

            $jsonResult = json_encode($widgets);

            $this->addSql("UPDATE credentials SET widgets = '$jsonResult' WHERE id = $id");
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
}
