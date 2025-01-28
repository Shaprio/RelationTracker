<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250128125158 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE interaction (id SERIAL NOT NULL, contact_id INT NOT NULL, interaction_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, initiated_by VARCHAR(50) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_378DFDA7E7A1254A ON interaction (contact_id)');
        $this->addSql('COMMENT ON COLUMN interaction.interaction_date IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE interaction ADD CONSTRAINT FK_378DFDA7E7A1254A FOREIGN KEY (contact_id) REFERENCES contact (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE interaction DROP CONSTRAINT FK_378DFDA7E7A1254A');
        $this->addSql('DROP TABLE interaction');
    }
}
