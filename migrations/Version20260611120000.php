<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create the short_link_click append-only log (durable click statistics).
 */
final class Version20260611120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create short_link_click table (append-only click log)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE short_link_click (id VARCHAR(255) NOT NULL, slug VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_short_link_click_slug ON short_link_click (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE short_link_click');
    }
}
