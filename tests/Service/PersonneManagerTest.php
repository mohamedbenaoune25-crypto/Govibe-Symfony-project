<?php

namespace App\Tests\Service;

use App\Entity\Personne;
use App\Service\PersonneManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service PersonneManager.
 * 
 * Vérifie les règles métier de validation de l'entité Personne :
 * 1. Le nom est obligatoire
 * 2. L'email doit être valide
 * 3. Le mot de passe doit contenir au moins 8 caractères
 */
class PersonneManagerTest extends TestCase
{
    /**
     * Test 1 : Une personne avec des données valides passe la validation.
     */
    public function testValidPersonne(): void
    {
        $personne = new Personne();
        $personne->setNom('Benaoune');
        $personne->setPrenom('Aziz');
        $personne->setEmail('aziz.benaoune@gmail.com');
        $personne->setPassword('MotDePasse123');

        $manager = new PersonneManager();

        $this->assertTrue($manager->validate($personne));
    }

    /**
     * Test 2 : Une personne sans nom doit lever une exception.
     */
    public function testPersonneWithoutName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire');

        $personne = new Personne();
        $personne->setEmail('test@gmail.com');
        $personne->setPassword('MotDePasse123');

        $manager = new PersonneManager();
        $manager->validate($personne);
    }

    /**
     * Test 3 : Une personne avec un email invalide doit lever une exception.
     */
    public function testPersonneWithInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email invalide');

        $personne = new Personne();
        $personne->setNom('Benaoune');
        $personne->setEmail('email_invalide');
        $personne->setPassword('MotDePasse123');

        $manager = new PersonneManager();
        $manager->validate($personne);
    }

    /**
     * Test 4 : Un mot de passe trop court doit lever une exception.
     */
    public function testPersonneWithShortPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le mot de passe doit contenir au moins 8 caractères');

        $personne = new Personne();
        $personne->setNom('Benaoune');
        $personne->setEmail('aziz@gmail.com');
        $personne->setPassword('abc');

        $manager = new PersonneManager();
        $manager->validate($personne);
    }

    /**
     * Test 5 : Un mot de passe vide doit lever une exception.
     */
    public function testPersonneWithEmptyPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le mot de passe doit contenir au moins 8 caractères');

        $personne = new Personne();
        $personne->setNom('Benaoune');
        $personne->setEmail('aziz@gmail.com');
        // Pas de setPassword — le mot de passe reste null

        $manager = new PersonneManager();
        $manager->validate($personne);
    }
}
