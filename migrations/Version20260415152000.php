<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415152000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for available languages';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE available_language (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(10) NOT NULL, name VARCHAR(80) NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, is_default TINYINT(1) DEFAULT 0 NOT NULL, created_at_utc DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", UNIQUE INDEX UNIQ_4A9142E977153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql("INSERT INTO available_language (code, name, enabled, is_default, created_at_utc) VALUES ('fr', 'Francais', 1, 1, NOW())");
        $this->addSql("INSERT INTO available_language (code, name, enabled, is_default, created_at_utc) VALUES ('en', 'English', 1, 0, NOW())");
        $this->addSql("INSERT INTO available_language (code, name, enabled, is_default, created_at_utc) VALUES ('es', 'Espanol', 0, 0, NOW())");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE available_language');
    }
}
