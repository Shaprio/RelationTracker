<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250127145110 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Safely drop contact.name if it exists (PostgreSQL)';
    }

    public function up(Schema $schema): void
    {
        // Upewnij się, że to Postgres (żeby IF EXISTS było wspierane tak jak oczekujemy)
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'Migration can only be executed safely on PostgreSQL.'
        );

        // Idempotentny drop kolumny
        $this->addSql('ALTER TABLE contact DROP COLUMN IF EXISTS name');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'postgresql',
            'Migration can only be executed safely on PostgreSQL.'
        );

        // Jeżeli chcesz NOT NULL, to rozważ domyślną wartość dla istniejących rekordów.
        // Najbezpieczniej: pozwól NULL (albo ustaw DEFAULT i potem ustaw NOT NULL).
        $this->addSql('ALTER TABLE contact ADD COLUMN IF NOT EXISTS name VARCHAR(255)');
    }
}
