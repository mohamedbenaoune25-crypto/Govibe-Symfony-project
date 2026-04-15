<?php

namespace App\Entity;

use App\Entity\Chambre;
use App\Repository\HotelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: HotelRepository::class)]
#[ORM\Table(name: 'hotel')]
class Hotel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: "Le nom de l'hotel est obligatoire.")]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: "Le nom de l'hotel doit contenir au moins {{ limit }} caracteres.",
        maxMessage: "Le nom de l'hotel ne peut pas depasser {{ limit }} caracteres."
    )]
    #[Assert\Regex(
        pattern: "/^[\p{L}0-9 .,'-]+$/u",
        message: "Le nom de l'hotel contient des caracteres invalides."
    )]
    private ?string $nom = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\NotBlank(message: "L'adresse est obligatoire.")]
    #[Assert\Length(
        min: 5,
        max: 150,
        minMessage: "L'adresse doit contenir au moins {{ limit }} caracteres.",
        maxMessage: "L'adresse ne peut pas depasser {{ limit }} caracteres."
    )]
    #[Assert\Regex(
        pattern: "/^[\p{L}0-9\s,.'\/-]+$/u",
        message: "L'adresse contient des caracteres invalides."
    )]
    private ?string $adresse = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: "La ville est obligatoire.")]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: "La ville doit contenir au moins {{ limit }} caracteres.",
        maxMessage: "La ville ne peut pas depasser {{ limit }} caracteres."
    )]
    #[Assert\Regex(
        pattern: "/^[\p{L}\s'\-]+$/u",
        message: "Le nom de la ville contient des caracteres invalides."
    )]
    private ?string $ville = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: "Le nombre d'etoiles doit etre compris entre {{ min }} et {{ max }}."
    )]
    private ?int $nombreEtoiles = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: "Le budget doit etre une valeur positive.")]
    private ?float $budget = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: "La description ne peut pas depasser {{ limit }} caracteres."
    )]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(message: "Veuillez saisir une URL valide.")]
    private ?string $photoUrl = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isFavoris = false;

    #[ORM\OneToMany(mappedBy: 'hotel', targetEntity: Chambre::class, cascade: ['remove'])]
    private Collection $chambres;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->chambres = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $this->normalizeText($nom);
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $this->normalizeText($adresse);
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $this->normalizeText($ville);
        return $this;
    }

    public function getNombreEtoiles(): ?int
    {
        return $this->nombreEtoiles;
    }

    public function setNombreEtoiles(?int $nombreEtoiles): self
    {
        $this->nombreEtoiles = $nombreEtoiles;
        return $this;
    }

    public function getBudget(): ?float
    {
        return $this->budget;
    }

    public function setBudget(?float $budget): self
    {
        $this->budget = $budget;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $this->normalizeText($description);
        return $this;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photoUrl;
    }

    public function setPhotoUrl(?string $photoUrl): self
    {
        $this->photoUrl = $this->normalizeText($photoUrl);
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Chambre>
     */
    public function getChambres(): Collection
    {
        return $this->chambres;
    }

    public function addChambre(Chambre $chambre): self
    {
        if (!$this->chambres->contains($chambre)) {
            $this->chambres->add($chambre);
            $chambre->setHotel($this);
        }

        return $this;
    }

    public function removeChambre(Chambre $chambre): self
    {
        if ($this->chambres->removeElement($chambre)) {
            // set the owning side to null (unless already changed)
            if ($chambre->getHotel() === $this) {
                $chambre->setHotel(null);
            }
        }

        return $this;
    }

    public function isFavoris(): bool
    {
        return $this->isFavoris;
    }

    public function setIsFavoris(bool $isFavoris): self
    {
        $this->isFavoris = $isFavoris;
        return $this;
    }

    public function toggleFavoris(): self
    {
        $this->isFavoris = !$this->isFavoris;
        return $this;
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? $value);

        return $normalized === '' ? null : $normalized;
    }
}
