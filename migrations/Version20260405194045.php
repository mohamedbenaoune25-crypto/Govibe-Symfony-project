<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405194045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hotel ADD is_favoris TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT 0');
        $this->addSql('ALTER TABLE poste ADD localisation VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hotel DROP is_favoris');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT \'0\'');
        $this->addSql('ALTER TABLE poste DROP localisation');
    }
}
