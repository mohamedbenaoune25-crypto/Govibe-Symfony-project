<?php

namespace App\Tests\Service;

use App\Entity\Location;
use App\Service\LocationManager;
use PHPUnit\Framework\TestCase;

class LocationManagerTest extends TestCase
{
    //  TEST 1 : Location valide
    public function testValidLocation(): void
    {
        $location = new Location();
        $location->setReference('LOC-2025-001');
        $location->setDateDebut(new \DateTime('2025-06-01'));
        $location->setDateFin(new \DateTime('2025-06-05'));
        $location->setNbJours(4);
        $location->setMontantTotal('320.00');

        $manager = new LocationManager();
        $this->assertTrue($manager->validate($location));
    }

    //  TEST 2 : Référence manquante
    public function testLocationSansReference(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $location = new Location();
        $location->setDateDebut(new \DateTime('2025-06-01'));
        $location->setDateFin(new \DateTime('2025-06-05'));
        $location->setNbJours(4);
        $location->setMontantTotal('320.00');

        $manager = new LocationManager();
        $manager->validate($location);
    }

    //  TEST 3 : dateFin avant dateDebut
    public function testLocationDateFinAvantDateDebut(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $location = new Location();
        $location->setReference('LOC-2025-002');
        $location->setDateDebut(new \DateTime('2025-06-10'));
        $location->setDateFin(new \DateTime('2025-06-05'));
        $location->setNbJours(4);
        $location->setMontantTotal('320.00');

        $manager = new LocationManager();
        $manager->validate($location);
    }

    //  TEST 4 : nbJours invalide
    public function testLocationNbJoursInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $location = new Location();
        $location->setReference('LOC-2025-003');
        $location->setDateDebut(new \DateTime('2025-06-01'));
        $location->setDateFin(new \DateTime('2025-06-05'));
        $location->setNbJours(0);
        $location->setMontantTotal('320.00');

        $manager = new LocationManager();
        $manager->validate($location);
    }

    //  TEST 5 : montantTotal invalide
    public function testLocationMontantInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $location = new Location();
        $location->setReference('LOC-2025-004');
        $location->setDateDebut(new \DateTime('2025-06-01'));
        $location->setDateFin(new \DateTime('2025-06-05'));
        $location->setNbJours(4);
        $location->setMontantTotal('0.00');

        $manager = new LocationManager();
        $manager->validate($location);
    }
}