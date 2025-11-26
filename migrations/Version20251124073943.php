<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251124073943 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE deployments ADD is_current_production BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE deployments ADD rollback_from_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE deployments ADD CONSTRAINT FK_373C43D548DBDA8A FOREIGN KEY (rollback_from_id) REFERENCES deployments (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_373C43D548DBDA8A ON deployments (rollback_from_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE deployments DROP CONSTRAINT FK_373C43D548DBDA8A');
        $this->addSql('DROP INDEX IDX_373C43D548DBDA8A');
        $this->addSql('ALTER TABLE deployments DROP is_current_production');
        $this->addSql('ALTER TABLE deployments DROP rollback_from_id');
    }
}
