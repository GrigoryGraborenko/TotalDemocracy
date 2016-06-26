<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160626123358 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE domain ADD parent_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE domain ADD CONSTRAINT FK_A7A91E0B727ACA70 FOREIGN KEY (parent_id) REFERENCES domain (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A7A91E0B727ACA70 ON domain (parent_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE domain DROP FOREIGN KEY FK_A7A91E0B727ACA70');
        $this->addSql('DROP INDEX IDX_A7A91E0B727ACA70 ON domain');
        $this->addSql('ALTER TABLE domain DROP parent_id');
    }
}
