<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122184801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users ADD github_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD github_access_token VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD avatar_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ALTER password DROP NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9D4327649 ON users (github_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_1483A5E9D4327649');
        $this->addSql('ALTER TABLE users DROP github_id');
        $this->addSql('ALTER TABLE users DROP github_access_token');
        $this->addSql('ALTER TABLE users DROP avatar_url');
        $this->addSql('ALTER TABLE users ALTER password SET NOT NULL');
    }
}
