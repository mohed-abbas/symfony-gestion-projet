<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260707061121 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_active flag to user (default true) for admin account activation';
    }

    public function up(Schema $schema): void
    {
        // DEFAULT true backfills existing rows and keeps new accounts active by default.
        $this->addSql('ALTER TABLE "user" ADD is_active BOOLEAN NOT NULL DEFAULT true');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE "user" DROP is_active');
    }
}
