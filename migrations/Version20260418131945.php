<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418131945 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hotel_description_translation ADD CONSTRAINT FK_BC44FAC53243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT 0');
        $this->addSql('ALTER TABLE reservation_session ADD status VARCHAR(20) DEFAULT \'confirmed\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hotel_description_translation DROP FOREIGN KEY FK_BC44FAC53243BB18');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT \'0\'');
        $this->addSql('ALTER TABLE reservation_session DROP status');
    }
}
