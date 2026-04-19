<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418145105 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activite_review (id INT AUTO_INCREMENT NOT NULL, rating INT NOT NULL, comment LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, activite_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_2145A29B0F88B1 (activite_id), INDEX IDX_2145A2A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE activite_review ADD CONSTRAINT FK_2145A29B0F88B1 FOREIGN KEY (activite_id) REFERENCES activite (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activite_review ADD CONSTRAINT FK_2145A2A76ED395 FOREIGN KEY (user_id) REFERENCES personne (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE activite ADD average_rating NUMERIC(3, 2) DEFAULT \'0.00\' NOT NULL, ADD review_count INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE hotel_description_translation ADD CONSTRAINT FK_BC44FAC53243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite_review DROP FOREIGN KEY FK_2145A29B0F88B1');
        $this->addSql('ALTER TABLE activite_review DROP FOREIGN KEY FK_2145A2A76ED395');
        $this->addSql('DROP TABLE activite_review');
        $this->addSql('ALTER TABLE activite DROP average_rating, DROP review_count');
        $this->addSql('ALTER TABLE hotel_description_translation DROP FOREIGN KEY FK_BC44FAC53243BB18');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT \'0\'');
    }
}
