<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415171000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove legacy available language table from removed translation integration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS available_language');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS available_language (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(10) NOT NULL, name VARCHAR(80) NOT NULL, enabled TINYINT(1) DEFAULT 1 NOT NULL, is_default TINYINT(1) DEFAULT 0 NOT NULL, created_at_utc DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)", UNIQUE INDEX UNIQ_4A9142E977153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }
}
