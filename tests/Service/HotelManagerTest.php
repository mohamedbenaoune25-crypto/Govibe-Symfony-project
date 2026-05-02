<?php

namespace App\Tests\Service;

use App\Entity\Hotel;
use App\Service\HotelManager;
use PHPUnit\Framework\TestCase;

class HotelManagerTest extends TestCase
{
    private HotelManager $manager;

    protected function setUp(): void
    {
        $this->manager = new HotelManager();
    }

    // ✅ CAS VALIDES

    public function testHotelValideComplet(): void
    {
        $hotel = new Hotel();
        $hotel->setNom('Le Grand Hôtel');
        $hotel->setAdresse('12 Rue de la Paix, Paris');
        $hotel->setNombreEtoiles(4);
        $hotel->setBudget(500.00);

        $this->assertTrue($this->manager->validate($hotel));
    }

    public function testHotelValideMinimal(): void
    {
        $hotel = new Hotel();
        $hotel->setNom('Hôtel Simple');
        $hotel->setAdresse('5 Avenue Victor Hugo');

        $this->assertTrue($this->manager->validate($hotel));
    }

    public function testBudgetZeroEstValide(): void
    {
        $hotel = new Hotel();
        $hotel->setNom('Hôtel Économique');
        $hotel->setAdresse('10 Rue principale');
        $hotel->setBudget(0);

        $this->assertTrue($this->manager->validate($hotel));
    }

    // ❌ CAS INVALIDES — Nom

    public function testNomManquantLeveException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom de l\'hôtel est obligatoire.');

        $hotel = new Hotel();
        $hotel->setAdresse('5 Avenue Victor Hugo');

        $this->manager->validate($hotel);
    }

    public function testNomTropCourtLeveException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom doit contenir au moins 2 caractères.');

        $hotel = new Hotel();
        $hotel->setNom('A');
        $hotel->setAdresse('5 Avenue Victor Hugo');

        $this->manager->validate($hotel);
    }

    // ❌ CAS INVALIDES — Adresse

    public function testAdresseManquanteLeveException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'adresse de l\'hôtel est obligatoire.');

        $hotel = new Hotel();
        $hotel->setNom('Hôtel Test');

        $this->manager->validate($hotel);
    }

    public function testAdresseTropCourteLeveException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'adresse doit contenir au moins 5 caractères.');

        $hotel = new Hotel();
        $hotel->setNom('Hôtel Test');
        $hotel->setAdresse('Rue');

        $this->manager->validate($hotel);
    }

    // ❌ CAS INVALIDES — Étoiles

    public function testZeroEtoilesLeveException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre d\'étoiles doit être entre 1 et 5.');

        $hotel = new Hotel();
        $hotel->setNom('Hôtel Test');
        $hotel->setAdresse('5 Avenue Victor Hugo');
        $hotel->setNombreEtoiles(0);

        $this->manager->validate($hotel);
    }

    public function testSixEtoilesLeveException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nombre d\'étoiles doit être entre 1 et 5.');

        $hotel = new Hotel();
        $hotel->setNom('Hôtel Test');
        $hotel->setAdresse('5 Avenue Victor Hugo');
        $hotel->setNombreEtoiles(6);

        $this->manager->validate($hotel);
    }

    // ❌ CAS INVALIDES — Budget

    public function testBudgetNegatifLeveException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le budget doit être une valeur positive ou nulle.');

        $hotel = new Hotel();
        $hotel->setNom('Hôtel Test');
        $hotel->setAdresse('5 Avenue Victor Hugo');
        $hotel->setBudget(-100);

        $this->manager->validate($hotel);
    }

    public function testBudgetTropEleveLeveException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le budget ne peut pas dépasser 1 000 000.');

        $hotel = new Hotel();
        $hotel->setNom('Hôtel Test');
        $hotel->setAdresse('5 Avenue Victor Hugo');
        $hotel->setBudget(1500000);

        $this->manager->validate($hotel);
    }
}
