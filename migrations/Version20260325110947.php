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
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE poste_likes DROP FOREIGN KEY `FK_FFC3BC98A0905086`');
        $this->addSql('ALTER TABLE poste_likes DROP FOREIGN KEY `FK_FFC3BC98A21BD112`');
        $this->addSql('DROP TABLE poste_likes');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT 0');
        $this->addSql('ALTER TABLE membre_forum ADD status VARCHAR(20) DEFAULT \'PENDING\' NOT NULL');
        $this->addSql('ALTER TABLE otp_codes DROP FOREIGN KEY `FK_7FF699E8A76ED395`');
        $this->addSql('DROP INDEX idx_user_id ON otp_codes');
        $this->addSql('CREATE INDEX IDX_7FF699E8A76ED395 ON otp_codes (user_id)');
        $this->addSql('ALTER TABLE otp_codes ADD CONSTRAINT `FK_7FF699E8A76ED395` FOREIGN KEY (user_id) REFERENCES personne (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_provider_id ON personne');
        $this->addSql('ALTER TABLE personne CHANGE role role VARCHAR(10) NOT NULL, CHANGE provider provider VARCHAR(50) DEFAULT NULL, CHANGE is_account_locked is_account_locked TINYINT NOT NULL, CHANGE preferred_mfa preferred_mfa VARCHAR(20) DEFAULT NULL');
        $this->addSql('DROP INDEX email ON personne');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FCEC9EFE7927C74 ON personne (email)');
        $this->addSql('ALTER TABLE poste DROP FOREIGN KEY `poste_ibfk_1`');
        $this->addSql('ALTER TABLE poste DROP FOREIGN KEY `poste_ibfk_2`');
        $this->addSql('ALTER TABLE poste CHANGE date_creation date_creation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE date_modification date_modification DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE contenu contenu LONGTEXT DEFAULT NULL');
        $this->addSql('DROP INDEX user_id ON poste');
        $this->addSql('CREATE INDEX IDX_7C890FABA76ED395 ON poste (user_id)');
        $this->addSql('DROP INDEX forum_id ON poste');
        $this->addSql('CREATE INDEX IDX_7C890FAB29CCBAD0 ON poste (forum_id)');
        $this->addSql('ALTER TABLE poste ADD CONSTRAINT `poste_ibfk_1` FOREIGN KEY (user_id) REFERENCES personne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poste ADD CONSTRAINT `poste_ibfk_2` FOREIGN KEY (forum_id) REFERENCES forum (forum_id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_status ON reclamation');
        $this->addSql('DROP INDEX idx_date_envoi ON reclamation');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY `fk_reclamation_user`');
        $this->addSql('ALTER TABLE reclamation CHANGE message message LONGTEXT NOT NULL, CHANGE reponse reponse LONGTEXT DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT \'EN_ATTENTE\' NOT NULL');
        $this->addSql('DROP INDEX idx_user_id ON reclamation');
        $this->addSql('CREATE INDEX IDX_CE606404A76ED395 ON reclamation (user_id)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT `fk_reclamation_user` FOREIGN KEY (user_id) REFERENCES personne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY `reservation_ibfk_1`');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY `reservation_ibfk_2`');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY `reservation_ibfk_3`');
        $this->addSql('ALTER TABLE reservation CHANGE statut statut VARCHAR(20) DEFAULT \'EN_ATTENTE\' NOT NULL');
        $this->addSql('DROP INDEX user_id ON reservation');
        $this->addSql('CREATE INDEX IDX_42C84955A76ED395 ON reservation (user_id)');
        $this->addSql('DROP INDEX chambre_id ON reservation');
        $this->addSql('CREATE INDEX IDX_42C849559B177F54 ON reservation (chambre_id)');
        $this->addSql('DROP INDEX hotel_id ON reservation');
        $this->addSql('CREATE INDEX IDX_42C849553243BB18 ON reservation (hotel_id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT `reservation_ibfk_1` FOREIGN KEY (user_id) REFERENCES personne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT `reservation_ibfk_2` FOREIGN KEY (chambre_id) REFERENCES chambre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT `reservation_ibfk_3` FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation_session DROP FOREIGN KEY `reservation_session_ibfk_1`');
        $this->addSql('DROP INDEX session_id ON reservation_session');
        $this->addSql('CREATE INDEX IDX_B7D16E51613FECDF ON reservation_session (session_id)');
        $this->addSql('ALTER TABLE reservation_session ADD CONSTRAINT `reservation_session_ibfk_1` FOREIGN KEY (session_id) REFERENCES sessions (id_session) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_is_active ON user_sessions');
        $this->addSql('ALTER TABLE user_sessions CHANGE login_date login_date DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE last_activity last_activity DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE is_active is_active TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE user_sessions ADD CONSTRAINT FK_7AED7913A76ED395 FOREIGN KEY (user_id) REFERENCES personne (id) ON DELETE CASCADE');
        $this->addSql('DROP INDEX idx_user_id ON user_sessions');
        $this->addSql('CREATE INDEX IDX_7AED7913A76ED395 ON user_sessions (user_id)');
        $this->addSql('ALTER TABLE voiture CHANGE type_carburant type_carburant VARCHAR(20) NOT NULL, CHANGE statut statut VARCHAR(20) DEFAULT \'DISPONIBLE\' NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE date_creation date_creation DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('DROP INDEX matricule ON voiture');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E9E2810F12B2DC9C ON voiture (matricule)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE poste_likes (poste_id INT NOT NULL, personne_id INT NOT NULL, INDEX IDX_FFC3BC98A0905086 (poste_id), INDEX IDX_FFC3BC98A21BD112 (personne_id), PRIMARY KEY (poste_id, personne_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE poste_likes ADD CONSTRAINT `FK_FFC3BC98A0905086` FOREIGN KEY (poste_id) REFERENCES poste (post_id)');
        $this->addSql('ALTER TABLE poste_likes ADD CONSTRAINT `FK_FFC3BC98A21BD112` FOREIGN KEY (personne_id) REFERENCES personne (id)');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT \'0\'');
        $this->addSql('ALTER TABLE membre_forum DROP status');
        $this->addSql('ALTER TABLE otp_codes DROP FOREIGN KEY FK_7FF699E8A76ED395');
        $this->addSql('DROP INDEX idx_7ff699e8a76ed395 ON otp_codes');
        $this->addSql('CREATE INDEX idx_user_id ON otp_codes (user_id)');
        $this->addSql('ALTER TABLE otp_codes ADD CONSTRAINT FK_7FF699E8A76ED395 FOREIGN KEY (user_id) REFERENCES personne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE personne CHANGE role role ENUM(\'admin\', \'user\') DEFAULT \'user\' NOT NULL, CHANGE provider provider VARCHAR(50) DEFAULT \'local\', CHANGE is_account_locked is_account_locked TINYINT DEFAULT 0, CHANGE preferred_mfa preferred_mfa VARCHAR(20) DEFAULT \'NONE\'');
        $this->addSql('CREATE INDEX idx_provider_id ON personne (provider_id)');
        $this->addSql('DROP INDEX uniq_fcec9efe7927c74 ON personne');
        $this->addSql('CREATE UNIQUE INDEX email ON personne (email)');
        $this->addSql('ALTER TABLE poste DROP FOREIGN KEY FK_7C890FABA76ED395');
        $this->addSql('ALTER TABLE poste DROP FOREIGN KEY FK_7C890FAB29CCBAD0');
        $this->addSql('ALTER TABLE poste CHANGE date_creation date_creation DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE date_modification date_modification DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE contenu contenu TEXT DEFAULT NULL');
        $this->addSql('DROP INDEX idx_7c890fab29ccbad0 ON poste');
        $this->addSql('CREATE INDEX forum_id ON poste (forum_id)');
        $this->addSql('DROP INDEX idx_7c890faba76ed395 ON poste');
        $this->addSql('CREATE INDEX user_id ON poste (user_id)');
        $this->addSql('ALTER TABLE poste ADD CONSTRAINT FK_7C890FABA76ED395 FOREIGN KEY (user_id) REFERENCES personne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE poste ADD CONSTRAINT FK_7C890FAB29CCBAD0 FOREIGN KEY (forum_id) REFERENCES forum (forum_id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reclamation DROP FOREIGN KEY FK_CE606404A76ED395');
        $this->addSql('ALTER TABLE reclamation CHANGE message message TEXT NOT NULL, CHANGE reponse reponse TEXT DEFAULT NULL, CHANGE status status VARCHAR(50) DEFAULT \'EN_ATTENTE\'');
        $this->addSql('CREATE INDEX idx_status ON reclamation (status)');
        $this->addSql('CREATE INDEX idx_date_envoi ON reclamation (date_envoi)');
        $this->addSql('DROP INDEX idx_ce606404a76ed395 ON reclamation');
        $this->addSql('CREATE INDEX idx_user_id ON reclamation (user_id)');
        $this->addSql('ALTER TABLE reclamation ADD CONSTRAINT FK_CE606404A76ED395 FOREIGN KEY (user_id) REFERENCES personne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955A76ED395');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849559B177F54');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849553243BB18');
        $this->addSql('ALTER TABLE reservation CHANGE statut statut ENUM(\'EN_ATTENTE\', \'CONFIRMEE\', \'ANNULEE\') DEFAULT \'EN_ATTENTE\'');
        $this->addSql('DROP INDEX idx_42c849553243bb18 ON reservation');
        $this->addSql('CREATE INDEX hotel_id ON reservation (hotel_id)');
        $this->addSql('DROP INDEX idx_42c84955a76ed395 ON reservation');
        $this->addSql('CREATE INDEX user_id ON reservation (user_id)');
        $this->addSql('DROP INDEX idx_42c849559b177f54 ON reservation');
        $this->addSql('CREATE INDEX chambre_id ON reservation (chambre_id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955A76ED395 FOREIGN KEY (user_id) REFERENCES personne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849559B177F54 FOREIGN KEY (chambre_id) REFERENCES chambre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849553243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reservation_session DROP FOREIGN KEY FK_B7D16E51613FECDF');
        $this->addSql('DROP INDEX idx_b7d16e51613fecdf ON reservation_session');
        $this->addSql('CREATE INDEX session_id ON reservation_session (session_id)');
        $this->addSql('ALTER TABLE reservation_session ADD CONSTRAINT FK_B7D16E51613FECDF FOREIGN KEY (session_id) REFERENCES sessions (id_session) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_sessions DROP FOREIGN KEY FK_7AED7913A76ED395');
        $this->addSql('ALTER TABLE user_sessions DROP FOREIGN KEY FK_7AED7913A76ED395');
        $this->addSql('ALTER TABLE user_sessions CHANGE login_date login_date DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE last_activity last_activity DATETIME DEFAULT CURRENT_TIMESTAMP, CHANGE is_active is_active TINYINT DEFAULT 1');
        $this->addSql('CREATE INDEX idx_is_active ON user_sessions (is_active)');
        $this->addSql('DROP INDEX idx_7aed7913a76ed395 ON user_sessions');
        $this->addSql('CREATE INDEX idx_user_id ON user_sessions (user_id)');
        $this->addSql('ALTER TABLE user_sessions ADD CONSTRAINT FK_7AED7913A76ED395 FOREIGN KEY (user_id) REFERENCES personne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE voiture CHANGE type_carburant type_carburant ENUM(\'Essence\', \'Diesel\', \'Hybride\', \'Electrique\') NOT NULL, CHANGE statut statut ENUM(\'DISPONIBLE\', \'LOUEE\', \'MAINTENANCE\') DEFAULT \'DISPONIBLE\', CHANGE description description TEXT DEFAULT NULL, CHANGE date_creation date_creation DATETIME DEFAULT CURRENT_TIMESTAMP');
        $this->addSql('DROP INDEX uniq_e9e2810f12b2dc9c ON voiture');
        $this->addSql('CREATE UNIQUE INDEX matricule ON voiture (matricule)');
    }
}
