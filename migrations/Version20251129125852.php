<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251129125852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add dns_provider as nullable first
        $this->addSql('ALTER TABLE domains ADD dns_provider VARCHAR(20) DEFAULT NULL');

        // Set default value for existing records
        $this->addSql("UPDATE domains SET dns_provider = 'manual' WHERE dns_provider IS NULL");

        // Make it NOT NULL after setting defaults
        $this->addSql('ALTER TABLE domains ALTER COLUMN dns_provider SET NOT NULL');

        // Add cloudflare_zone_id
        $this->addSql('ALTER TABLE domains ADD cloudflare_zone_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE domains DROP dns_provider');
        $this->addSql('ALTER TABLE domains DROP cloudflare_zone_id');
    }
}
