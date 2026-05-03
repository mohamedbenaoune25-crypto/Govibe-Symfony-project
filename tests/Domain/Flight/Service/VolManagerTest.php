<?php

namespace App\Tests\Domain\Flight\Service;

use App\Domain\Flight\Entity\Vol;
use App\Domain\Flight\Service\VolManager;
use PHPUnit\Framework\TestCase;

class VolManagerTest extends TestCase
{
    public function testVolValide(): void
    {
        $vol = new Vol();
        $vol->setFlightId('TU123');
        $vol->setPrix(350);
        $vol->setAvailableSeats(120);
        $vol->setDepartureTime(new \DateTime('08:00'));
        $vol->setArrivalTime(new \DateTime('10:00'));

        $manager = new VolManager();
        $this->assertTrue($manager->validate($vol));
    }

    public function testVolSansFlightId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $vol = new Vol();
        $vol->setPrix(200);
        $vol->setAvailableSeats(50);

        $manager = new VolManager();
        $manager->validate($vol);
    }

    public function testVolAvecPrixNegatif(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $vol = new Vol();
        $vol->setFlightId('TU456');
        $vol->setPrix(-100);
        $vol->setAvailableSeats(80);

        $manager = new VolManager();
        $manager->validate($vol);
    }

    public function testVolAvecSeatsNegatifs(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $vol = new Vol();
        $vol->setFlightId('TU321');
        $vol->setPrix(150);
        $vol->setAvailableSeats(-5);

        $manager = new VolManager();
        $manager->validate($vol);
    }

    public function testVolAvecArriveAvantDepart(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $vol = new Vol();
        $vol->setFlightId('TU654');
        $vol->setPrix(500);
        $vol->setAvailableSeats(200);
        $vol->setDepartureTime(new \DateTime('14:00'));
        $vol->setArrivalTime(new \DateTime('12:00'));

        $manager = new VolManager();
        $manager->validate($vol);
    }
}
