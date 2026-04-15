<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260413110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nombre_de_chambres column to chambre table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chambre ADD nombre_de_chambres INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chambre DROP nombre_de_chambres');
    }
}
