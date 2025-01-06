<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250106123958 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contact (id SERIAL NOT NULL, user_name_id INT NOT NULL, email_c VARCHAR(255) DEFAULT NULL, phone VARCHAR(255) DEFAULT NULL, birthday DATE DEFAULT NULL, address VARCHAR(255) DEFAULT NULL, note VARCHAR(255) DEFAULT NULL, relationship VARCHAR(255) DEFAULT NULL, profile_picture VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, update_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_4C62E638291A82DC ON contact (user_name_id)');
        $this->addSql('COMMENT ON COLUMN contact.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE event (id SERIAL NOT NULL, user_e_id INT NOT NULL, title VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, date DATE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, update_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_3BAE0AA7CE3D88C6 ON event (user_e_id)');
        $this->addSql('COMMENT ON COLUMN event.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE event_contact (id SERIAL NOT NULL, event_id INT DEFAULT NULL, contact_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C9354F9771F7E88B ON event_contact (event_id)');
        $this->addSql('CREATE INDEX IDX_C9354F97E7A1254A ON event_contact (contact_id)');
        $this->addSql('CREATE TABLE reminder (id SERIAL NOT NULL, user_r_id INT DEFAULT NULL, contact_r_id INT DEFAULT NULL, event_r_id INT DEFAULT NULL, message VARCHAR(255) NOT NULL, remind_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_40374F403F3E7E0 ON reminder (user_r_id)');
        $this->addSql('CREATE INDEX IDX_40374F40518D29D6 ON reminder (contact_r_id)');
        $this->addSql('CREATE INDEX IDX_40374F4066B9E782 ON reminder (event_r_id)');
        $this->addSql('COMMENT ON COLUMN reminder.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE setting (id SERIAL NOT NULL, useruser_s_id INT DEFAULT NULL, user_id INT NOT NULL, notifications BOOLEAN NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9F74B898750221E5 ON setting (useruser_s_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9F74B898A76ED395 ON setting (user_id)');
        $this->addSql('CREATE TABLE "user" (id SERIAL NOT NULL, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, roles JSON NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');
        $this->addSql('COMMENT ON COLUMN "user".created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E638291A82DC FOREIGN KEY (user_name_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA7CE3D88C6 FOREIGN KEY (user_e_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event_contact ADD CONSTRAINT FK_C9354F9771F7E88B FOREIGN KEY (event_id) REFERENCES event (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE event_contact ADD CONSTRAINT FK_C9354F97E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reminder ADD CONSTRAINT FK_40374F403F3E7E0 FOREIGN KEY (user_r_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reminder ADD CONSTRAINT FK_40374F40518D29D6 FOREIGN KEY (contact_r_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE reminder ADD CONSTRAINT FK_40374F4066B9E782 FOREIGN KEY (event_r_id) REFERENCES event (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE setting ADD CONSTRAINT FK_9F74B898750221E5 FOREIGN KEY (useruser_s_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE setting ADD CONSTRAINT FK_9F74B898A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE contact DROP CONSTRAINT FK_4C62E638291A82DC');
        $this->addSql('ALTER TABLE event DROP CONSTRAINT FK_3BAE0AA7CE3D88C6');
        $this->addSql('ALTER TABLE event_contact DROP CONSTRAINT FK_C9354F9771F7E88B');
        $this->addSql('ALTER TABLE event_contact DROP CONSTRAINT FK_C9354F97E7A1254A');
        $this->addSql('ALTER TABLE reminder DROP CONSTRAINT FK_40374F403F3E7E0');
        $this->addSql('ALTER TABLE reminder DROP CONSTRAINT FK_40374F40518D29D6');
        $this->addSql('ALTER TABLE reminder DROP CONSTRAINT FK_40374F4066B9E782');
        $this->addSql('ALTER TABLE setting DROP CONSTRAINT FK_9F74B898750221E5');
        $this->addSql('ALTER TABLE setting DROP CONSTRAINT FK_9F74B898A76ED395');
        $this->addSql('DROP TABLE contact');
        $this->addSql('DROP TABLE event');
        $this->addSql('DROP TABLE event_contact');
        $this->addSql('DROP TABLE reminder');
        $this->addSql('DROP TABLE setting');
        $this->addSql('DROP TABLE "user"');
    }
}
