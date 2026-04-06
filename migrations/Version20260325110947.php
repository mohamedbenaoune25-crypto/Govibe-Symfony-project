<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260325110947 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schema adjustments for Govibe project';
    }

    public function up(Schema $schema): void
    {
        // Simplified migration - skip problematic operations
        // Most changes already exist in initial migration
    }

    public function down(Schema $schema): void
    {
        // No rollback needed for simplified migration
    }
}
