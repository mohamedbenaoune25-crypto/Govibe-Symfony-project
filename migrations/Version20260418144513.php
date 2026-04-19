<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418144513 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite ADD opening_time TIME DEFAULT \'09:00:00\', ADD closing_time TIME DEFAULT \'18:00:00\', ADD best_moment VARCHAR(20) DEFAULT NULL, ADD weather_type VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE hotel_description_translation ADD CONSTRAINT FK_BC44FAC53243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite DROP opening_time, DROP closing_time, DROP best_moment, DROP weather_type');
        $this->addSql('ALTER TABLE hotel_description_translation DROP FOREIGN KEY FK_BC44FAC53243BB18');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT \'0\'');
    }
}
