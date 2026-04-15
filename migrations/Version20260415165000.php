<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415165000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legacy description translation table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS description_translation');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS description_translation (id INT AUTO_INCREMENT NOT NULL, entity_type VARCHAR(50) NOT NULL, entity_id VARCHAR(100) NOT NULL, field_name VARCHAR(80) NOT NULL, source_locale VARCHAR(10) NOT NULL, target_locale VARCHAR(10) NOT NULL, translated_text LONGTEXT NOT NULL, updated_at_utc DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", UNIQUE INDEX uniq_desc_translation (entity_type, entity_id, field_name, target_locale), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
}
