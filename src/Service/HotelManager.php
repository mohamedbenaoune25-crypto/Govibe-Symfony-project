<?php

namespace App\Service;

use App\Entity\Hotel;

class HotelManager
{
    public function validate(Hotel $hotel): bool
    {
        // Règle 1 : Nom obligatoire (min 2 caractères)
        if (empty($hotel->getNom())) {
            throw new \InvalidArgumentException('Le nom de l\'hôtel est obligatoire.');
        }
        if (strlen($hotel->getNom()) < 2) {
            throw new \InvalidArgumentException('Le nom doit contenir au moins 2 caractères.');
        }

        // Règle 2 : Adresse obligatoire (min 5 caractères)
        if (empty($hotel->getAdresse())) {
            throw new \InvalidArgumentException('L\'adresse de l\'hôtel est obligatoire.');
        }
        if (strlen($hotel->getAdresse()) < 5) {
            throw new \InvalidArgumentException('L\'adresse doit contenir au moins 5 caractères.');
        }

        // Règle 3 : Étoiles entre 1 et 5
        $etoiles = $hotel->getNombreEtoiles();
        if ($etoiles !== null && ($etoiles < 1 || $etoiles > 5)) {
            throw new \InvalidArgumentException('Le nombre d\'étoiles doit être entre 1 et 5.');
        }

        // Règle 4 : Budget positif ou nul
        $budget = $hotel->getBudget();
        if ($budget !== null && $budget < 0) {
            throw new \InvalidArgumentException('Le budget doit être une valeur positive ou nulle.');
        }

        // Règle 5 : Budget max 1 000 000
        if ($budget !== null && $budget > 1000000) {
            throw new \InvalidArgumentException('Le budget ne peut pas dépasser 1 000 000.');
        }

        return true;
    }
}