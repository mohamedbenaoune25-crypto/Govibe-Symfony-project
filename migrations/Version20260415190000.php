<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260415190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend hotel translation table with nom/adresse/ville and make description nullable';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel_description_translation ADD nom VARCHAR(150) DEFAULT NULL, ADD adresse VARCHAR(200) DEFAULT NULL, ADD ville VARCHAR(120) DEFAULT NULL, CHANGE description description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel_description_translation DROP nom, DROP adresse, DROP ville, CHANGE description description LONGTEXT NOT NULL');
    }
}
