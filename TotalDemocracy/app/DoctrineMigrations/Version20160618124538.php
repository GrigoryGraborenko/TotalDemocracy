<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160618124538 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE document (id VARCHAR(255) NOT NULL, domain_id VARCHAR(255) NOT NULL, when_created DATETIME NOT NULL, type VARCHAR(255) NOT NULL, state VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, summary LONGTEXT NOT NULL, date_created DATETIME NOT NULL, external_id VARCHAR(255) DEFAULT NULL, external_url VARCHAR(255) DEFAULT NULL, custom_data LONGTEXT DEFAULT NULL, text LONGTEXT DEFAULT NULL, INDEX IDX_D8698A76115F0EE5 (domain_id), INDEX external_idx (external_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE domain (id VARCHAR(255) NOT NULL, date_created DATETIME NOT NULL, level VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, short_name VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE electoral_roll_import (id VARCHAR(255) NOT NULL, date_created DATETIME NOT NULL, valid_date DATETIME NOT NULL, surname VARCHAR(255) NOT NULL, given_names VARCHAR(255) NOT NULL, json LONGTEXT NOT NULL, unit_number INT DEFAULT NULL, street_number INT DEFAULT NULL, street VARCHAR(255) DEFAULT NULL, street_type VARCHAR(255) DEFAULT NULL, suburb VARCHAR(255) DEFAULT NULL, dob DATETIME DEFAULT NULL, INDEX import_idx (valid_date, surname, given_names), INDEX surname_idx (surname), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE electorate (id VARCHAR(255) NOT NULL, domain_id VARCHAR(255) NOT NULL, date_created DATETIME NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, INDEX IDX_393F623B115F0EE5 (domain_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE options (id VARCHAR(255) NOT NULL, date_created DATETIME NOT NULL, name VARCHAR(255) NOT NULL, value VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_D035FA875E237E06 (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE server_events (id VARCHAR(255) NOT NULL, user_id VARCHAR(255) DEFAULT NULL, parent_id VARCHAR(255) DEFAULT NULL, date_created DATETIME NOT NULL, name VARCHAR(255) NOT NULL, amount NUMERIC(10, 2) DEFAULT NULL, processed TINYINT(1) NOT NULL, json LONGTEXT DEFAULT NULL, INDEX IDX_2AFB6E8CA76ED395 (user_id), INDEX IDX_2AFB6E8C727ACA70 (parent_id), INDEX name_idx (name), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_record (id VARCHAR(255) NOT NULL, volunteer_id VARCHAR(255) DEFAULT NULL, username VARCHAR(255) NOT NULL, username_canonical VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, email_canonical VARCHAR(255) NOT NULL, enabled TINYINT(1) NOT NULL, salt VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, last_login DATETIME DEFAULT NULL, locked TINYINT(1) NOT NULL, expired TINYINT(1) NOT NULL, expires_at DATETIME DEFAULT NULL, confirmation_token VARCHAR(255) DEFAULT NULL, password_requested_at DATETIME DEFAULT NULL, roles LONGTEXT NOT NULL COMMENT \'(DC2Type:array)\', credentials_expired TINYINT(1) NOT NULL, credentials_expire_at DATETIME DEFAULT NULL, date_created DATETIME NOT NULL, given_names VARCHAR(255) DEFAULT NULL, surname VARCHAR(255) DEFAULT NULL, postcode VARCHAR(255) DEFAULT NULL, suburb VARCHAR(255) DEFAULT NULL, street VARCHAR(255) DEFAULT NULL, street_number VARCHAR(255) DEFAULT NULL, dob DATETIME DEFAULT NULL, when_verified DATETIME DEFAULT NULL, phone VARCHAR(255) DEFAULT NULL, is_volunteer TINYINT(1) NOT NULL, is_member TINYINT(1) NOT NULL, permanent_login_token VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_FE6684AC92FC23A8 (username_canonical), UNIQUE INDEX UNIQ_FE6684ACA0D96FBF (email_canonical), UNIQUE INDEX UNIQ_FE6684AC8EFAB6B1 (volunteer_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_electorate (user_id VARCHAR(255) NOT NULL, electorate_id VARCHAR(255) NOT NULL, INDEX IDX_9E58A4D7A76ED395 (user_id), INDEX IDX_9E58A4D7AFD2EB60 (electorate_id), PRIMARY KEY(user_id, electorate_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user_document_vote (id VARCHAR(255) NOT NULL, user_id VARCHAR(255) DEFAULT NULL, document_id VARCHAR(255) DEFAULT NULL, date_created DATETIME NOT NULL, is_supporter TINYINT(1) NOT NULL, INDEX IDX_132AA4A7A76ED395 (user_id), INDEX IDX_132AA4A7C33F7837 (document_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE volunteer (id VARCHAR(255) NOT NULL, date_created DATETIME NOT NULL, home_postcode VARCHAR(255) NOT NULL, home_suburb VARCHAR(255) NOT NULL, home_street VARCHAR(255) NOT NULL, home_street_number VARCHAR(255) NOT NULL, will_poll_booth TINYINT(1) NOT NULL, will_door_knock TINYINT(1) NOT NULL, will_signage TINYINT(1) NOT NULL, will_call TINYINT(1) NOT NULL, will_house_party TINYINT(1) NOT NULL, will_envelopes TINYINT(1) NOT NULL, will_other LONGTEXT DEFAULT NULL, when_available LONGTEXT DEFAULT NULL, when_to_call VARCHAR(255) DEFAULT NULL, best_communication VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE document ADD CONSTRAINT FK_D8698A76115F0EE5 FOREIGN KEY (domain_id) REFERENCES domain (id)');
        $this->addSql('ALTER TABLE electorate ADD CONSTRAINT FK_393F623B115F0EE5 FOREIGN KEY (domain_id) REFERENCES domain (id)');
        $this->addSql('ALTER TABLE server_events ADD CONSTRAINT FK_2AFB6E8CA76ED395 FOREIGN KEY (user_id) REFERENCES user_record (id)');
        $this->addSql('ALTER TABLE server_events ADD CONSTRAINT FK_2AFB6E8C727ACA70 FOREIGN KEY (parent_id) REFERENCES server_events (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_record ADD CONSTRAINT FK_FE6684AC8EFAB6B1 FOREIGN KEY (volunteer_id) REFERENCES volunteer (id)');
        $this->addSql('ALTER TABLE user_electorate ADD CONSTRAINT FK_9E58A4D7A76ED395 FOREIGN KEY (user_id) REFERENCES user_record (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_electorate ADD CONSTRAINT FK_9E58A4D7AFD2EB60 FOREIGN KEY (electorate_id) REFERENCES electorate (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_document_vote ADD CONSTRAINT FK_132AA4A7A76ED395 FOREIGN KEY (user_id) REFERENCES user_record (id)');
        $this->addSql('ALTER TABLE user_document_vote ADD CONSTRAINT FK_132AA4A7C33F7837 FOREIGN KEY (document_id) REFERENCES document (id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user_document_vote DROP FOREIGN KEY FK_132AA4A7C33F7837');
        $this->addSql('ALTER TABLE document DROP FOREIGN KEY FK_D8698A76115F0EE5');
        $this->addSql('ALTER TABLE electorate DROP FOREIGN KEY FK_393F623B115F0EE5');
        $this->addSql('ALTER TABLE user_electorate DROP FOREIGN KEY FK_9E58A4D7AFD2EB60');
        $this->addSql('ALTER TABLE server_events DROP FOREIGN KEY FK_2AFB6E8C727ACA70');
        $this->addSql('ALTER TABLE server_events DROP FOREIGN KEY FK_2AFB6E8CA76ED395');
        $this->addSql('ALTER TABLE user_electorate DROP FOREIGN KEY FK_9E58A4D7A76ED395');
        $this->addSql('ALTER TABLE user_document_vote DROP FOREIGN KEY FK_132AA4A7A76ED395');
        $this->addSql('ALTER TABLE user_record DROP FOREIGN KEY FK_FE6684AC8EFAB6B1');
        $this->addSql('DROP TABLE document');
        $this->addSql('DROP TABLE domain');
        $this->addSql('DROP TABLE electoral_roll_import');
        $this->addSql('DROP TABLE electorate');
        $this->addSql('DROP TABLE options');
        $this->addSql('DROP TABLE server_events');
        $this->addSql('DROP TABLE user_record');
        $this->addSql('DROP TABLE user_electorate');
        $this->addSql('DROP TABLE user_document_vote');
        $this->addSql('DROP TABLE volunteer');
    }
}
