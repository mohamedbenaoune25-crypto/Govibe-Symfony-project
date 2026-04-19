<?php

namespace App\Entity;

use App\Repository\PersonneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: PersonneRepository::class)]
#[ORM\Table(name: 'personne')]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse email.')]
class Personne implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    private ?string $prenom = null;

    #[ORM\Column(length: 150, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: 'string', length: 10)]
    private ?string $role = 'user';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $provider = 'local';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $photoUrl = null;

    #[ORM\Column(type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?bool $isAccountLocked = false;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $preferredMfa = 'NONE';

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lockoutUntil = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $faceEncoding = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /** @deprecated use getUserIdentifier() instead */
    public function getUsername(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = [$this->role === 'admin' ? 'ROLE_ADMIN' : 'ROLE_USER'];
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getSalt(): ?string
    {
        return null; // Not needed with bcrypt/argon2
    }

    public function eraseCredentials(): void
    {
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(?string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function getProviderId(): ?string
    {
        return $this->providerId;
    }

    public function setProviderId(?string $providerId): self
    {
        $this->providerId = $providerId;
        return $this;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function setPhotoUrl(?string $photoUrl): self
    {
        $this->photoUrl = $photoUrl;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isAccountLocked(): ?bool
    {
        return $this->isAccountLocked;
    }

    public function setIsAccountLocked(bool $isAccountLocked): self
    {
        $this->isAccountLocked = $isAccountLocked;
        return $this;
    }

    public function getPreferredMfa(): ?string
    {
        return $this->preferredMfa;
    }

    public function setPreferredMfa(?string $preferredMfa): self
    {
        $this->preferredMfa = $preferredMfa;
        return $this;
    }

    public function getLockoutUntil(): ?\DateTimeInterface
    {
        return $this->lockoutUntil;
    }

    public function setLockoutUntil(?\DateTimeInterface $lockoutUntil): self
    {
        $this->lockoutUntil = $lockoutUntil;
        return $this;
    }

    public function getFaceEncoding(): ?array
    {
        return $this->faceEncoding;
    }

    public function setFaceEncoding(?array $faceEncoding): self
    {
        $this->faceEncoding = $faceEncoding;
        return $this;
    }
}
