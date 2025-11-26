<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122222717 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add columns as nullable first
        $this->addSql('ALTER TABLE projects ADD webhook_secret VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE projects ADD github_webhook_secret VARCHAR(100) DEFAULT NULL');

        // Generate unique webhook secrets for existing projects using md5 + random
        $this->addSql("UPDATE projects SET webhook_secret = md5(random()::text || clock_timestamp()::text || id::text) || md5(random()::text || id::text) WHERE webhook_secret IS NULL");

        // Now make webhook_secret NOT NULL and add unique index
        $this->addSql('ALTER TABLE projects ALTER COLUMN webhook_secret SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5C93B3A475F1F198 ON projects (webhook_secret)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_5C93B3A475F1F198');
        $this->addSql('ALTER TABLE projects DROP webhook_secret');
        $this->addSql('ALTER TABLE projects DROP github_webhook_secret');
    }
}
