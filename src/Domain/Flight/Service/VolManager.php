<?php

namespace App\Domain\Flight\Service;

use App\Domain\Flight\Entity\Vol;

class VolManager
{
    public function validate(Vol $vol): bool
    {
        if (empty($vol->getFlightId())) {
            throw new \InvalidArgumentException('Le numéro de vol est obligatoire.');
        }

        if ($vol->getPrix() === null || $vol->getPrix() <= 0) {
            throw new \InvalidArgumentException('Le prix doit être supérieur à zéro.');
        }

        if ($vol->getAvailableSeats() === null || $vol->getAvailableSeats() < 0) {
            throw new \InvalidArgumentException('Le nombre de sièges disponibles ne peut pas être négatif.');
        }

        if ($vol->getDepartureTime() !== null && $vol->getArrivalTime() !== null) {
            if ($vol->getArrivalTime() <= $vol->getDepartureTime()) {
                throw new \InvalidArgumentException("L'heure d'arrivée doit être postérieure à l'heure de départ.");
            }
        }

        return true;
    }
}