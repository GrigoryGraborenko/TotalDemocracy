<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20170113063855 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE document ADD date_updated DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE domain ADD date_updated DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE electoral_roll_import ADD date_updated DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE electorate ADD date_updated DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE options ADD date_updated DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE server_events ADD date_updated DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE tasks ADD date_updated DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user_record ADD date_updated DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX names_idx ON user_record (given_names, surname)');
        $this->addSql('ALTER TABLE user_document_vote ADD date_updated DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE volunteer ADD date_updated DATETIME DEFAULT NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE document DROP date_updated');
        $this->addSql('ALTER TABLE domain DROP date_updated');
        $this->addSql('ALTER TABLE electoral_roll_import DROP date_updated');
        $this->addSql('ALTER TABLE electorate DROP date_updated');
        $this->addSql('ALTER TABLE options DROP date_updated');
        $this->addSql('ALTER TABLE server_events DROP date_updated');
        $this->addSql('ALTER TABLE tasks DROP date_updated');
        $this->addSql('ALTER TABLE user_document_vote DROP date_updated');
        $this->addSql('DROP INDEX names_idx ON user_record');
        $this->addSql('ALTER TABLE user_record DROP date_updated');
        $this->addSql('ALTER TABLE volunteer DROP date_updated');
    }
}
