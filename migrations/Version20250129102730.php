<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250129102730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE setting DROP CONSTRAINT fk_9f74b898750221e5');
        $this->addSql('DROP INDEX uniq_9f74b898750221e5');
        $this->addSql('ALTER TABLE setting ADD dark_mode BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE setting ADD font_size VARCHAR(10) DEFAULT NULL');
        $this->addSql('ALTER TABLE setting DROP useruser_s_id');
        $this->addSql('ALTER TABLE setting ALTER notifications SET DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE setting ADD useruser_s_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE setting DROP dark_mode');
        $this->addSql('ALTER TABLE setting DROP font_size');
        $this->addSql('ALTER TABLE setting ALTER notifications DROP DEFAULT');
        $this->addSql('ALTER TABLE setting ADD CONSTRAINT fk_9f74b898750221e5 FOREIGN KEY (useruser_s_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX uniq_9f74b898750221e5 ON setting (useruser_s_id)');
    }
}
