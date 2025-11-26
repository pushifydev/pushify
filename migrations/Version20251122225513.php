<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251122225513 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE projects ADD container_port INT DEFAULT NULL');
        $this->addSql('ALTER TABLE projects ADD container_id VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE projects ADD server_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE projects ADD CONSTRAINT FK_5C93B3A41844E6B7 FOREIGN KEY (server_id) REFERENCES servers (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_5C93B3A41844E6B7 ON projects (server_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE projects DROP CONSTRAINT FK_5C93B3A41844E6B7');
        $this->addSql('DROP INDEX IDX_5C93B3A41844E6B7');
        $this->addSql('ALTER TABLE projects DROP container_port');
        $this->addSql('ALTER TABLE projects DROP container_id');
        $this->addSql('ALTER TABLE projects DROP server_id');
    }
}
