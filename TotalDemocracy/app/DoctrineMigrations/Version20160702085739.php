<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160702085739 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE electoral_roll_import ADD from_ocr TINYINT(1) DEFAULT \'0\' NOT NULL');
        $this->addSql('ALTER TABLE server_events DROP FOREIGN KEY FK_2AFB6E8CA76ED395');
        $this->addSql('ALTER TABLE server_events ADD CONSTRAINT FK_2AFB6E8CA76ED395 FOREIGN KEY (user_id) REFERENCES user_record (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_document_vote DROP FOREIGN KEY FK_132AA4A7A76ED395');
        $this->addSql('ALTER TABLE user_document_vote ADD CONSTRAINT FK_132AA4A7A76ED395 FOREIGN KEY (user_id) REFERENCES user_record (id) ON DELETE CASCADE');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE electoral_roll_import DROP from_ocr');
        $this->addSql('ALTER TABLE server_events DROP FOREIGN KEY FK_2AFB6E8CA76ED395');
        $this->addSql('ALTER TABLE server_events ADD CONSTRAINT FK_2AFB6E8CA76ED395 FOREIGN KEY (user_id) REFERENCES user_record (id)');
        $this->addSql('ALTER TABLE user_document_vote DROP FOREIGN KEY FK_132AA4A7A76ED395');
        $this->addSql('ALTER TABLE user_document_vote ADD CONSTRAINT FK_132AA4A7A76ED395 FOREIGN KEY (user_id) REFERENCES user_record (id)');
    }
}
