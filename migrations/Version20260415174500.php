<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415174500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create hotel description translation table for FR EN AR interface';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hotel_description_translation (id INT AUTO_INCREMENT NOT NULL, hotel_id INT NOT NULL, locale VARCHAR(5) NOT NULL, description LONGTEXT NOT NULL, updated_at_utc DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", INDEX IDX_8029590832DA2B (hotel_id), UNIQUE INDEX uniq_hotel_locale_translation (hotel_id, locale), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hotel_description_translation ADD CONSTRAINT FK_8029590832DA2B FOREIGN KEY (hotel_id) REFERENCES hotel (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE hotel_description_translation');
    }
}
