<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260418141854 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE coupon (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, discount_value NUMERIC(10, 2) NOT NULL, type VARCHAR(10) NOT NULL, valid_until DATETIME DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, UNIQUE INDEX UNIQ_64BF3F0277153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE hotel_description_translation ADD CONSTRAINT FK_BC44FAC53243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT 0');
        $this->addSql('ALTER TABLE personne ADD customer_type VARCHAR(20) DEFAULT \'standard\' NOT NULL, ADD subscription_expires_at DATETIME DEFAULT NULL, ADD session_credits INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE reservation_session ADD paid_amount NUMERIC(10, 2) DEFAULT \'0.00\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE coupon');
        $this->addSql('ALTER TABLE hotel_description_translation DROP FOREIGN KEY FK_BC44FAC53243BB18');
        $this->addSql('ALTER TABLE login_attempts CHANGE risk_score risk_score DOUBLE PRECISION DEFAULT \'0\'');
        $this->addSql('ALTER TABLE personne DROP customer_type, DROP subscription_expires_at, DROP session_credits');
        $this->addSql('ALTER TABLE reservation_session DROP paid_amount');
    }
}
