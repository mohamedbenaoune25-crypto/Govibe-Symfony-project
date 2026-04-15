<?php

namespace App\Security;

use App\Entity\Personne;
use App\Repository\PersonneRepository;
use Doctrine\ORM\EntityManagerInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Custom OAuth User Provider for HWIOAuthBundle.
 *
 * Handles Google OAuth authentication:
 * - Finds existing users by email or Google provider ID
 * - Auto-creates new accounts from Google profile data
 * - Updates provider info and photo on each login
 */
class GoogleOAuthUserProvider implements OAuthAwareUserProviderInterface, UserProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PersonneRepository $personneRepository,
    ) {
    }

    /**
     * Called after successful OAuth handshake.
     * Loads existing user or creates a new one from Google profile.
     */
    public function loadUserByOAuthUserResponse(UserResponseInterface $response): UserInterface
    {
        $googleId = $response->getUsername(); // Google unique ID
        $email = $response->getEmail();
        $firstName = $response->getFirstName() ?? '';
        $lastName = $response->getLastName() ?? '';
        $photoUrl = $response->getProfilePicture();

        // 1. Try to find by Google provider ID
        $user = $this->personneRepository->findOneBy([
            'provider'   => 'google',
            'providerId' => $googleId,
        ]);

        // 2. If not found by provider ID, try by email
        if (null === $user && $email) {
            $user = $this->personneRepository->findOneBy(['email' => $email]);
        }

        // 3. If user exists, update OAuth info
        if (null !== $user) {
            $user->setProvider('google');
            $user->setProviderId($googleId);

            if ($photoUrl) {
                $user->setPhotoUrl($photoUrl);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $user;
        }

        // 4. Create a brand new user from Google profile
        $user = new Personne();
        $user->setEmail($email);
        $user->setPrenom($firstName ?: 'Utilisateur');
        $user->setNom($lastName ?: 'Google');
        $user->setProvider('google');
        $user->setProviderId($googleId);
        $user->setPhotoUrl($photoUrl);
        $user->setRole('user');
        // No password for OAuth-only accounts
        $user->setPassword(null);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Loads a user by their unique identifier (email).
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->personneRepository->findOneBy(['email' => $identifier]);

        if (null === $user) {
            throw new \Symfony\Component\Security\Core\Exception\UserNotFoundException(
                sprintf('User with email "%s" not found.', $identifier)
            );
        }

        return $user;
    }

    /**
     * Refreshes the user from the session.
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Personne) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user))
            );
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    /**
     * Whether this provider supports the given user class.
     */
    public function supportsClass(string $class): bool
    {
        return Personne::class === $class || is_subclass_of($class, Personne::class);
    }
}
