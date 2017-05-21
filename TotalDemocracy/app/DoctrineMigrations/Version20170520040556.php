<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170520040556 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE newsletter (id VARCHAR(255) NOT NULL, task_group_id VARCHAR(255) NOT NULL, date_created DATETIME NOT NULL, date_updated DATETIME DEFAULT NULL, type VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, json_components LONGTEXT NOT NULL, sent TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_7E8585C8BE94330B (task_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE task_group (id VARCHAR(255) NOT NULL, date_created DATETIME NOT NULL, date_updated DATETIME DEFAULT NULL, ready TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE task_group ADD json_params LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE task_group ADD type VARCHAR(255) NOT NULL');

        $this->addSql('ALTER TABLE newsletter ADD CONSTRAINT FK_7E8585C8BE94330B FOREIGN KEY (task_group_id) REFERENCES task_group (id)');
        $this->addSql('ALTER TABLE tasks ADD task_group_id VARCHAR(255) DEFAULT NULL, ADD ready TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT FK_50586597BE94330B FOREIGN KEY (task_group_id) REFERENCES task_group (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_50586597BE94330B ON tasks (task_group_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE newsletter DROP FOREIGN KEY FK_7E8585C8BE94330B');
        $this->addSql('ALTER TABLE tasks DROP FOREIGN KEY FK_50586597BE94330B');
        $this->addSql('DROP TABLE newsletter');
        $this->addSql('DROP TABLE task_group');
        $this->addSql('DROP INDEX IDX_50586597BE94330B ON tasks');
        $this->addSql('ALTER TABLE tasks DROP task_group_id, DROP ready');
    }
}
