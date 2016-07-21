<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160713065151 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE tasks (id VARCHAR(255) NOT NULL, user_id VARCHAR(255) DEFAULT NULL, date_created DATETIME NOT NULL, type VARCHAR(255) NOT NULL, service VARCHAR(255) NOT NULL, function VARCHAR(255) NOT NULL, json_params LONGTEXT NOT NULL, json_result LONGTEXT DEFAULT NULL, min_seconds NUMERIC(10, 4) NOT NULL, when_processed DATETIME DEFAULT NULL, INDEX IDX_50586597A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE tasks ADD CONSTRAINT FK_50586597A76ED395 FOREIGN KEY (user_id) REFERENCES user_record (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_record ADD email_opt_out TINYINT(1) DEFAULT \'0\' NOT NULL, ADD when_from_nation_builder DATETIME DEFAULT NULL, ADD when_sent_to_nation_builder DATETIME DEFAULT NULL, ADD json LONGTEXT DEFAULT NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE tasks');
        $this->addSql('ALTER TABLE user_record DROP email_opt_out, DROP when_from_nation_builder, DROP when_sent_to_nation_builder, DROP json');
    }
}
