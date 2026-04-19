<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418150130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite ADD latitude NUMERIC(10, 8) DEFAULT NULL, ADD longitude NUMERIC(11, 8) DEFAULT NULL');
        $this->addSql('ALTER TABLE activite_review ADD CONSTRAINT FK_2145A29B0F88B1 FOREIGN KEY (activite_id) REFERENCES activite (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activite_review ADD CONSTRAINT FK_2145A2A76ED395 FOREIGN KEY (user_id) REFERENCES personne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hotel_description_translation ADD CONSTRAINT FK_BC44FAC53243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite DROP latitude, DROP longitude');
        $this->addSql('ALTER TABLE activite_review DROP FOREIGN KEY FK_2145A29B0F88B1');
        $this->addSql('ALTER TABLE activite_review DROP FOREIGN KEY FK_2145A2A76ED395');
        $this->addSql('ALTER TABLE hotel_description_translation DROP FOREIGN KEY FK_BC44FAC53243BB18');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT \'0\'');
    }
}
