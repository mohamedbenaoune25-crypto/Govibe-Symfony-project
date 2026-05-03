<?php

namespace App\Service;

use App\Entity\Location;

class LocationManager
{
    public function validate(Location $location): bool
    {
        // Règle 1 : référence obligatoire
        if (empty($location->getReference())) {
            throw new \InvalidArgumentException('La référence est obligatoire.');
        }

        // Règle 2 : dateFin > dateDebut
        if ($location->getDateFin() <= $location->getDateDebut()) {
            throw new \InvalidArgumentException(
                'La date de fin doit être postérieure à la date de début.'
            );
        }

        // Règle 3 : nbJours > 0
        if ($location->getNbJours() <= 0) {
            throw new \InvalidArgumentException(
                'Le nombre de jours doit être supérieur à zéro.'
            );
        }

        // Règle 4 : montantTotal > 0
        if ((float) $location->getMontantTotal() <= 0) {
            throw new \InvalidArgumentException(
                'Le montant total doit être supérieur à zéro.'
            );
        }

        return true;
    }
}