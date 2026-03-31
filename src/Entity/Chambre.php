<?php

namespace App\Entity;

use App\Repository\ChambreRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChambreRepository::class)]
#[ORM\Table(name: 'chambre')]
class Chambre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    private ?int $capacite = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $equipements = null;

    #[ORM\Column(nullable: true)]
    private ?float $prixStandard = null;

    #[ORM\Column(nullable: true)]
    private ?float $prixHauteSaison = null;

    #[ORM\Column(nullable: true)]
    private ?float $prixBasseSaison = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: Hotel::class, inversedBy: 'chambres')]
    #[ORM\JoinColumn(name: 'hotel_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
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
        $this->type = $type;
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

    public function getEquipements(): ?string
    {
        return $this->equipements;
    }

    public function setEquipements(?string $equipements): self
    {
        $this->equipements = $equipements;
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
}
