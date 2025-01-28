<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250128092305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE recurring_event (id SERIAL NOT NULL, title VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, recurrence_pattern VARCHAR(50) NOT NULL, start_date DATE NOT NULL, end_date DATE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('COMMENT ON COLUMN recurring_event.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE TABLE recurring_event_contact (id SERIAL NOT NULL, recurring_event_id INT NOT NULL, contact_id INT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_C9FB8F14E54B259A ON recurring_event_contact (recurring_event_id)');
        $this->addSql('CREATE INDEX IDX_C9FB8F14E7A1254A ON recurring_event_contact (contact_id)');
        $this->addSql('ALTER TABLE recurring_event_contact ADD CONSTRAINT FK_C9FB8F14E54B259A FOREIGN KEY (recurring_event_id) REFERENCES recurring_event (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE recurring_event_contact ADD CONSTRAINT FK_C9FB8F14E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE recurring_event_contact DROP CONSTRAINT FK_C9FB8F14E54B259A');
        $this->addSql('ALTER TABLE recurring_event_contact DROP CONSTRAINT FK_C9FB8F14E7A1254A');
        $this->addSql('DROP TABLE recurring_event');
        $this->addSql('DROP TABLE recurring_event_contact');
    }
}
