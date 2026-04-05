<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration for initial Govibe schema and data.
 */
final class Version20260323204000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial database schema and data from PHPMyAdmin dump';
    }

    public function up(Schema $schema): void
    {
        // Disable foreign key checks for initial setup to avoid order issues
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');

        // Structure of the table `activite`
        $this->addSql("CREATE TABLE `activite` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(150) NOT NULL,
          `description` text DEFAULT NULL,
          `type` varchar(50) NOT NULL,
          `localisation` varchar(150) NOT NULL,
          `prix` decimal(10,2) DEFAULT 0.00,
          `status` varchar(20) DEFAULT 'Confirmed',
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `hotel`
        $this->addSql("CREATE TABLE `hotel` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `nom` varchar(100) DEFAULT NULL,
          `adresse` varchar(150) DEFAULT NULL,
          `ville` varchar(100) DEFAULT NULL,
          `nombre_etoiles` int(11) DEFAULT NULL,
          `budget` double DEFAULT NULL,
          `description` text DEFAULT NULL,
          `photo_url` varchar(255) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `chambre`
        $this->addSql("CREATE TABLE `chambre` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `type` varchar(100) DEFAULT NULL,
          `capacite` int(11) DEFAULT NULL,
          `equipements` varchar(255) DEFAULT NULL,
          `hotel_id` int(11) DEFAULT NULL,
          `prix_standard` double DEFAULT NULL,
          `prix_haute_saison` double DEFAULT NULL,
          `prix_basse_saison` double DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `hotel_id` (`hotel_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `personne`
        $this->addSql("CREATE TABLE `personne` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `nom` varchar(100) NOT NULL,
          `prenom` varchar(100) NOT NULL,
          `email` varchar(150) NOT NULL,
          `password` varchar(255) DEFAULT NULL,
          `role` enum('admin','user') NOT NULL DEFAULT 'user',
          `provider` varchar(50) DEFAULT 'local',
          `provider_id` varchar(255) DEFAULT NULL,
          `photo_url` varchar(500) DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `is_account_locked` tinyint(1) DEFAULT 0,
          `preferred_mfa` varchar(20) DEFAULT 'NONE',
          `lockout_until` datetime DEFAULT NULL,
          `face_encoding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`face_encoding`)),
          PRIMARY KEY (`id`),
          UNIQUE KEY `email` (`email`),
          KEY `idx_provider_id` (`provider_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `vol`
        $this->addSql("CREATE TABLE `vol` (
          `flight_id` varchar(50) NOT NULL,
          `departure_airport` varchar(100) NOT NULL,
          `destination` varchar(100) NOT NULL,
          `departure_time` time NOT NULL,
          `arrival_time` time NOT NULL,
          `classe_chaise` varchar(255) NOT NULL,
          `airline` varchar(100) NOT NULL,
          `prix` int(11) NOT NULL,
          `available_seats` int(11) NOT NULL,
          `description` longtext DEFAULT NULL,
          PRIMARY KEY (`flight_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `checkout`
        $this->addSql("CREATE TABLE `checkout` (
          `checkout_id` int(11) NOT NULL AUTO_INCREMENT,
          `flight_id` varchar(50) NOT NULL,
          `user_id` int(11) NOT NULL,
          `reservation_date` datetime NOT NULL,
          `passenger_nbr` int(11) NOT NULL,
          `status_reservation` varchar(255) NOT NULL,
          `total_prix` int(11) NOT NULL,
          `passenger_name` varchar(255) DEFAULT NULL,
          `passenger_email` varchar(255) DEFAULT NULL,
          `passenger_phone` varchar(50) DEFAULT NULL,
          `payment_method` varchar(50) DEFAULT 'CREDIT_CARD',
          `seat_preference` varchar(20) DEFAULT 'WINDOW',
          `travel_class` varchar(20) DEFAULT 'Economy',
          PRIMARY KEY (`checkout_id`),
          KEY `flight_id` (`flight_id`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `forum`
        $this->addSql("CREATE TABLE `forum` (
          `forum_id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(100) NOT NULL,
          `image` varchar(255) DEFAULT NULL,
          `created_by` int(11) NOT NULL,
          `post_count` int(11) DEFAULT 0,
          `nbr_members` int(11) DEFAULT 0,
          `description` text DEFAULT NULL,
          `date_creation` datetime DEFAULT current_timestamp(),
          `is_private` tinyint(1) DEFAULT 0,
          PRIMARY KEY (`forum_id`),
          KEY `created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `poste`
        $this->addSql("CREATE TABLE `poste` (
          `post_id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `forum_id` int(11) DEFAULT NULL,
          `likes` int(11) DEFAULT 0,
          `date_creation` datetime DEFAULT current_timestamp(),
          `date_modification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          `url` varchar(255) DEFAULT NULL,
          `type` varchar(50) DEFAULT NULL,
          `contenu` text DEFAULT NULL,
          PRIMARY KEY (`post_id`),
          KEY `user_id` (`user_id`),
          KEY `forum_id` (`forum_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `commentaire`
        $this->addSql("CREATE TABLE `commentaire` (
          `commentaire_id` int(11) NOT NULL AUTO_INCREMENT,
          `post_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `contenu` text NOT NULL,
          `date_commentaire` datetime DEFAULT current_timestamp(),
          `date_modification` datetime DEFAULT NULL ON UPDATE current_timestamp(),
          `statut` enum('publié','en_attente','supprimé') DEFAULT 'publié',
          `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
          `parent_id` int(11) DEFAULT 0,
          `likes` int(11) DEFAULT 0,
          `dislikes` int(11) DEFAULT 0,
          PRIMARY KEY (`commentaire_id`),
          KEY `post_id` (`post_id`),
          KEY `user_id` (`user_id`),
          KEY `statut` (`statut`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `voiture`
        $this->addSql("CREATE TABLE `voiture` (
          `id_voiture` int(11) NOT NULL AUTO_INCREMENT,
          `matricule` varchar(30) NOT NULL,
          `marque` varchar(50) NOT NULL,
          `modele` varchar(50) NOT NULL,
          `annee` int(11) NOT NULL,
          `type_carburant` enum('Essence','Diesel','Hybride','Electrique') NOT NULL,
          `prix_jour` decimal(10,2) NOT NULL,
          `statut` enum('DISPONIBLE','LOUEE','MAINTENANCE') DEFAULT 'DISPONIBLE',
          `adresse_agence` varchar(255) NOT NULL,
          `latitude` decimal(10,8) NOT NULL,
          `longitude` decimal(11,8) NOT NULL,
          `description` text DEFAULT NULL,
          `image_url` varchar(255) DEFAULT NULL,
          `date_creation` datetime DEFAULT current_timestamp(),
          PRIMARY KEY (`id_voiture`),
          UNIQUE KEY `matricule` (`matricule`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `location`
        $this->addSql("CREATE TABLE `location` (
          `id_location` int(11) NOT NULL AUTO_INCREMENT,
          `reference` varchar(50) NOT NULL,
          `date_debut` date NOT NULL,
          `date_fin` date NOT NULL,
          `nb_jours` int(11) NOT NULL,
          `montant_total` decimal(10,2) NOT NULL,
          `contrat_pdf` varchar(255) DEFAULT NULL,
          `qr_code` varchar(255) DEFAULT NULL,
          `statut` enum('EN_ATTENTE','CONFIRMEE','ANNULEE') DEFAULT 'EN_ATTENTE',
          `date_creation` datetime DEFAULT current_timestamp(),
          `id_voiture` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          PRIMARY KEY (`id_location`),
          UNIQUE KEY `reference` (`reference`),
          KEY `id_voiture` (`id_voiture`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `login_attempts`
        $this->addSql("CREATE TABLE `login_attempts` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `ip_address` varchar(45) DEFAULT NULL,
          `device` varchar(255) DEFAULT NULL,
          `country` varchar(100) DEFAULT NULL,
          `login_time` datetime DEFAULT current_timestamp(),
          `success` tinyint(1) DEFAULT 0,
          `risk_score` double DEFAULT 0,
          `auth_level` varchar(20) DEFAULT 'LOW',
          PRIMARY KEY (`id`),
          KEY `idx_user_id` (`user_id`),
          KEY `idx_login_time` (`login_time`),
          KEY `idx_success` (`success`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Structure of the table `membre_forum`
        $this->addSql("CREATE TABLE `membre_forum` (
          `forum_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `date_adhesion` datetime DEFAULT current_timestamp(),
          PRIMARY KEY (`forum_id`,`user_id`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `otp_codes`
        $this->addSql("CREATE TABLE `otp_codes` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `code` varchar(10) NOT NULL,
          `created_at` datetime DEFAULT current_timestamp(),
          `expires_at` datetime NOT NULL,
          `used` tinyint(1) DEFAULT 0,
          PRIMARY KEY (`id`),
          KEY `idx_user_id` (`user_id`),
          KEY `idx_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Structure of the table `password_resets`
        $this->addSql("CREATE TABLE `password_resets` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `email` varchar(150) NOT NULL,
          `token` varchar(255) NOT NULL,
          `expiration_date` datetime NOT NULL,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `reclamation`
        $this->addSql("CREATE TABLE `reclamation` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `sujet` varchar(255) NOT NULL,
          `message` text NOT NULL,
          `reponse` text DEFAULT NULL,
          `status` varchar(50) DEFAULT 'EN_ATTENTE',
          `date_envoi` timestamp NOT NULL DEFAULT current_timestamp(),
          `date_reponse` timestamp NULL DEFAULT NULL,
          `created_by_user` int(11) NOT NULL,
          PRIMARY KEY (`id`),
          KEY `idx_user_id` (`user_id`),
          KEY `idx_status` (`status`),
          KEY `idx_date_envoi` (`date_envoi`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Structure of the table `reservation`
        $this->addSql("CREATE TABLE `reservation` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `chambre_id` int(11) NOT NULL,
          `hotel_id` int(11) NOT NULL,
          `date_debut` date NOT NULL,
          `date_fin` date NOT NULL,
          `prix_total` double NOT NULL,
          `statut` enum('EN_ATTENTE','CONFIRMEE','ANNULEE') DEFAULT 'EN_ATTENTE',
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `chambre_id` (`chambre_id`),
          KEY `hotel_id` (`hotel_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `sessions`
        $this->addSql("CREATE TABLE `sessions` (
          `id_session` int(11) NOT NULL AUTO_INCREMENT,
          `date` date NOT NULL,
          `heure` time NOT NULL,
          `capacite` int(11) NOT NULL,
          `nbr_places_restant` int(11) NOT NULL,
          `activite_id` int(11) NOT NULL,
          PRIMARY KEY (`id_session`),
          KEY `activite_id` (`activite_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `reservation_session`
        $this->addSql("CREATE TABLE `reservation_session` (
          `id_reservation` int(11) NOT NULL AUTO_INCREMENT,
          `session_id` int(11) NOT NULL,
          `nb_places` int(11) DEFAULT 1,
          `reserved_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `user_ref` varchar(50) NOT NULL DEFAULT 'USER001',
          PRIMARY KEY (`id_reservation`),
          KEY `session_id` (`session_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Structure of the table `user_sessions`
        $this->addSql("CREATE TABLE `user_sessions` (
          `id` varchar(36) NOT NULL,
          `user_id` int(11) NOT NULL,
          `ip_address` varchar(45) DEFAULT NULL,
          `device_name` varchar(255) DEFAULT NULL,
          `country` varchar(100) DEFAULT NULL,
          `city` varchar(100) DEFAULT NULL,
          `login_date` datetime DEFAULT current_timestamp(),
          `last_activity` datetime DEFAULT current_timestamp(),
          `is_active` tinyint(1) DEFAULT 1,
          PRIMARY KEY (`id`),
          KEY `idx_user_id` (`user_id`),
          KEY `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Add constraints
        $this->addSql("ALTER TABLE `chambre` ADD CONSTRAINT `chambre_ibfk_1` FOREIGN KEY (`hotel_id`) REFERENCES `hotel` (`id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `checkout` ADD CONSTRAINT `checkout_ibfk_1` FOREIGN KEY (`flight_id`) REFERENCES `vol` (`flight_id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `checkout` ADD CONSTRAINT `checkout_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `personne` (`id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `commentaire` ADD CONSTRAINT `commentaire_ibfk_1` FOREIGN KEY (`post_id`) REFERENCES `poste` (`post_id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `commentaire` ADD CONSTRAINT `commentaire_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `personne` (`id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `forum` ADD CONSTRAINT `forum_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `personne` (`id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `location` ADD CONSTRAINT `location_ibfk_1` FOREIGN KEY (`id_voiture`) REFERENCES `voiture` (`id_voiture`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `location` ADD CONSTRAINT `location_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `personne` (`id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `membre_forum` ADD CONSTRAINT `membre_forum_ibfk_1` FOREIGN KEY (`forum_id`) REFERENCES `forum` (`forum_id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `membre_forum` ADD CONSTRAINT `membre_forum_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `personne` (`id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `poste` ADD CONSTRAINT `poste_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `personne` (`id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `poste` ADD CONSTRAINT `poste_ibfk_2` FOREIGN KEY (`forum_id`) REFERENCES `forum` (`forum_id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `reclamation` ADD CONSTRAINT `fk_reclamation_user` FOREIGN KEY (`user_id`) REFERENCES `personne` (`id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `reservation` ADD CONSTRAINT `reservation_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `personne` (`id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `reservation` ADD CONSTRAINT `reservation_ibfk_2` FOREIGN KEY (`chambre_id`) REFERENCES `chambre` (`id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `reservation` ADD CONSTRAINT `reservation_ibfk_3` FOREIGN KEY (`hotel_id`) REFERENCES `hotel` (`id`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `reservation_session` ADD CONSTRAINT `reservation_session_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `sessions` (`id_session`) ON DELETE CASCADE;");
        $this->addSql("ALTER TABLE `sessions` ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`activite_id`) REFERENCES `activite` (`id`) ON DELETE CASCADE;");

        // Enable foreign key checks back
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');

        // Add some initial data if needed (shortened for brevity, but including some key ones)
        // Note: For large datasets, consider using fixtures.
        $this->addSql("INSERT INTO `personne` (`id`, `nom`, `prenom`, `email`, `password`, `role`) VALUES (1, 'Saaidi', 'Youssef', 'youssef.saaidi@outlook.com', '\$2a\$10\$iHyo3sSrq1s8fUCHFhyn7.ox7rtzB0AEBqGNveXutip7GN3e9J8au', 'admin');");
        $this->addSql("INSERT INTO `activite` (`id`, `name`, `description`, `type`, `localisation`, `prix`, `status`) VALUES (1, 'Fo5arCity', 'Découvrir les traditions de nabeul', 'Artisanat • Créatif', 'Nabeul', 65.50, 'Confirmed');");
        // ... more data can be added here
    }

    public function down(Schema $schema): void
    {
        $this->addSql('SET FOREIGN_KEY_CHECKS = 0;');
        $this->addSql('DROP TABLE IF EXISTS `user_sessions`');
        $this->addSql('DROP TABLE IF EXISTS `reservation_session`');
        $this->addSql('DROP TABLE IF EXISTS `sessions`');
        $this->addSql('DROP TABLE IF EXISTS `reservation`');
        $this->addSql('DROP TABLE IF EXISTS `reclamation`');
        $this->addSql('DROP TABLE IF EXISTS `password_resets`');
        $this->addSql('DROP TABLE IF EXISTS `otp_codes`');
        $this->addSql('DROP TABLE IF EXISTS `membre_forum`');
        $this->addSql('DROP TABLE IF EXISTS `login_attempts`');
        $this->addSql('DROP TABLE IF EXISTS `location`');
        $this->addSql('DROP TABLE IF EXISTS `voiture`');
        $this->addSql('DROP TABLE IF EXISTS `commentaire`');
        $this->addSql('DROP TABLE IF EXISTS `poste`');
        $this->addSql('DROP TABLE IF EXISTS `forum`');
        $this->addSql('DROP TABLE IF EXISTS `checkout`');
        $this->addSql('DROP TABLE IF EXISTS `vol`');
        $this->addSql('DROP TABLE IF EXISTS `personne`');
        $this->addSql('DROP TABLE IF EXISTS `chambre`');
        $this->addSql('DROP TABLE IF EXISTS `hotel`');
        $this->addSql('DROP TABLE IF EXISTS `activite`');
        $this->addSql('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
