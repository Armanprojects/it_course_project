<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Full-text search over positions.
 *
 * The vector is a stored generated column rather than a trigger or an
 * application-maintained field: PostgreSQL keeps it in step with the row by
 * itself, so no write path can forget to reindex.
 */
final class Version20260831090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add a generated tsvector column and GIN index for position full-text search';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform,
            'This migration is written for PostgreSQL.',
        );

        // 'simple' instead of a language dictionary: titles mix English and
        // Russian, and stemming with one wrong dictionary loses more matches
        // than the stemming wins.
        $this->addSql(<<<'SQL'
            ALTER TABLE "position"
            ADD COLUMN search_vector tsvector
            GENERATED ALWAYS AS (
                setweight(to_tsvector('simple', coalesce(title, '')), 'A') ||
                setweight(to_tsvector('simple', coalesce(company, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(level, '')), 'C') ||
                setweight(to_tsvector('simple', coalesce(short_description, '')), 'D')
            ) STORED
            SQL);

        $this->addSql('CREATE INDEX idx_position_search ON "position" USING GIN (search_vector)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_position_search');
        $this->addSql('ALTER TABLE "position" DROP COLUMN IF EXISTS search_vector');
    }
}
