<?php

namespace App\Service;

use App\Entity\Personne;

/**
 * Service métier de validation de l'entité Personne (Authentification).
 * 
 * Règles métier :
 * 1. Le nom est obligatoire
 * 2. L'email doit être un format valide
 * 3. Le mot de passe doit contenir au moins 8 caractères
 */
class PersonneManager
{
    /**
     * Valide les données d'une Personne selon les règles métier.
     *
     * @param Personne $personne L'entité Personne à valider
     * @return bool true si la validation réussit
     * @throws \InvalidArgumentException si une règle métier n'est pas respectée
     */
    public function validate(Personne $personne): bool
    {
        // Règle 1 : Le nom est obligatoire
        if (empty($personne->getNom())) {
            throw new \InvalidArgumentException('Le nom est obligatoire');
        }

        // Règle 2 : L'email doit être valide
        if (!filter_var($personne->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }

        // Règle 3 : Le mot de passe doit contenir au moins 8 caractères
        $password = $personne->getPassword();
        if (empty($password) || strlen($password) < 8) {
            throw new \InvalidArgumentException('Le mot de passe doit contenir au moins 8 caractères');
        }

        return true;
    }
}
