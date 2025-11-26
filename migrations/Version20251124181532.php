<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251124181532 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE deployments ALTER commit_message TYPE TEXT');
        $this->addSql('ALTER TABLE deployments ALTER docker_image TYPE TEXT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE deployments ALTER commit_message TYPE VARCHAR(255)');
        $this->addSql('ALTER TABLE deployments ALTER docker_image TYPE VARCHAR(255)');
    }
}
