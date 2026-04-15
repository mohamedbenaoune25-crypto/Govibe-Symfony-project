<?php

namespace App\Entity;

use App\Repository\ChambreRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ChambreRepository::class)]
#[ORM\Table(name: 'chambre')]
class Chambre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: "Le type de chambre est obligatoire.")]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: "Le type de chambre doit contenir au moins {{ limit }} caracteres.",
        maxMessage: "Le type de chambre ne peut pas depasser {{ limit }} caracteres."
    )]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: "La capacite est obligatoire.")]
    #[Assert\Positive(message: "La capacite doit etre superieure a 0.")]
    #[Assert\Range(
        min: 1,
        max: 20,
        notInRangeMessage: "La capacite doit etre comprise entre {{ min }} et {{ max }}."
    )]
    private ?int $capacite = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: "Le nombre de chambres est obligatoire.")]
    #[Assert\Positive(message: "Le nombre de chambres doit etre superieur a 0.")]
    #[Assert\Range(
        min: 1,
        max: 500,
        notInRangeMessage: "Le nombre de chambres doit etre compris entre {{ min }} et {{ max }}."
    )]
    private ?int $nombreDeChambres = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: "Les equipements ne peuvent pas depasser {{ limit }} caracteres."
    )]
    private ?string $equipements = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: "Le prix standard doit etre positif ou nul.")]
    private ?float $prixStandard = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: "Le prix haute saison doit etre positif ou nul.")]
    private ?float $prixHauteSaison = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: "Le prix basse saison doit etre positif ou nul.")]
    private ?float $prixBasseSaison = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Hotel::class, inversedBy: 'chambres')]
    #[ORM\JoinColumn(name: 'hotel_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Assert\NotNull(message: "L'hotel associe est obligatoire.")]
    private ?Hotel $hotel = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $this->normalizeText($type);
        return $this;
    }

    public function getCapacite(): ?int
    {
        return $this->capacite;
    }

    public function setCapacite(?int $capacite): self
    {
        $this->capacite = $capacite;
        return $this;
    }

    public function getNombreDeChambres(): ?int
    {
        return $this->nombreDeChambres;
    }

    public function setNombreDeChambres(?int $nombreDeChambres): self
    {
        $this->nombreDeChambres = $nombreDeChambres;
        return $this;
    }

    public function getEquipements(): ?string
    {
        return $this->equipements;
    }

    public function setEquipements(?string $equipements): self
    {
        $this->equipements = $this->normalizeText($equipements);
        return $this;
    }

    public function getPrixStandard(): ?float
    {
        return $this->prixStandard;
    }

    public function setPrixStandard(?float $prixStandard): self
    {
        $this->prixStandard = $prixStandard;
        return $this;
    }

    public function getPrixHauteSaison(): ?float
    {
        return $this->prixHauteSaison;
    }

    public function setPrixHauteSaison(?float $prixHauteSaison): self
    {
        $this->prixHauteSaison = $prixHauteSaison;
        return $this;
    }

    public function getPrixBasseSaison(): ?float
    {
        return $this->prixBasseSaison;
    }

    public function setPrixBasseSaison(?float $prixBasseSaison): self
    {
        $this->prixBasseSaison = $prixBasseSaison;
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

    public function getHotel(): ?Hotel
    {
        return $this->hotel;
    }

    public function setHotel(?Hotel $hotel): self
    {
        $this->hotel = $hotel;
        return $this;
    }

    #[Assert\Callback]
    public function validatePricing(ExecutionContextInterface $context): void
    {
        $standard = (float) ($this->prixStandard ?? 0);
        $high = (float) ($this->prixHauteSaison ?? 0);
        $low = (float) ($this->prixBasseSaison ?? 0);

        if ($standard <= 0 && $high <= 0 && $low <= 0) {
            $context->buildViolation('Au moins un prix (standard, haute saison ou basse saison) doit etre strictement positif.')
                ->atPath('prixStandard')
                ->addViolation();
        }
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
