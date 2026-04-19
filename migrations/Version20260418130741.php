<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418130741 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE hotel_description_translation (id INT AUTO_INCREMENT NOT NULL, locale VARCHAR(5) NOT NULL, nom VARCHAR(150) DEFAULT NULL, adresse VARCHAR(200) DEFAULT NULL, ville VARCHAR(120) DEFAULT NULL, description LONGTEXT DEFAULT NULL, updated_at_utc DATETIME NOT NULL, hotel_id INT NOT NULL, INDEX IDX_BC44FAC53243BB18 (hotel_id), UNIQUE INDEX uniq_hotel_locale_translation (hotel_id, locale), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE hotel_description_translation ADD CONSTRAINT FK_BC44FAC53243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chambre ADD nombre_de_chambres INT DEFAULT NULL');
        $this->addSql('ALTER TABLE checkout ADD stripe_session_id VARCHAR(255) DEFAULT NULL, ADD paid_at DATETIME DEFAULT NULL, ADD signature LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT 0');
        $this->addSql('ALTER TABLE personne CHANGE face_encoding face_encoding JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE sessions ADD created_by INT DEFAULT NULL');
        $this->addSql('ALTER TABLE sessions ADD CONSTRAINT FK_9A609D13DE12AB56 FOREIGN KEY (created_by) REFERENCES personne (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_9A609D13DE12AB56 ON sessions (created_by)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE hotel_description_translation DROP FOREIGN KEY FK_BC44FAC53243BB18');
        $this->addSql('DROP TABLE hotel_description_translation');
        $this->addSql('ALTER TABLE chambre DROP nombre_de_chambres');
        $this->addSql('ALTER TABLE checkout DROP stripe_session_id, DROP paid_at, DROP signature');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT \'0\'');
        $this->addSql('ALTER TABLE personne CHANGE face_encoding face_encoding LONGTEXT DEFAULT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE sessions DROP FOREIGN KEY FK_9A609D13DE12AB56');
        $this->addSql('DROP INDEX IDX_9A609D13DE12AB56 ON sessions');
        $this->addSql('ALTER TABLE sessions DROP created_by');
    }
}
