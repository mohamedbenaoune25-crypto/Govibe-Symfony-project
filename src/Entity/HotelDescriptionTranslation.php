<?php

namespace App\Entity;

use App\Repository\HotelDescriptionTranslationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HotelDescriptionTranslationRepository::class)]
#[ORM\Table(name: 'hotel_description_translation')]
#[ORM\UniqueConstraint(name: 'uniq_hotel_locale_translation', columns: ['hotel_id', 'locale'])]
class HotelDescriptionTranslation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Hotel::class)]
    #[ORM\JoinColumn(name: 'hotel_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Hotel $hotel = null;

    #[ORM\Column(length: 5)]
    private ?string $locale = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'updated_at_utc', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAtUtc = null;

    public function __construct()
    {
        $this->updatedAtUtc = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHotel(): ?Hotel
    {
        return $this->hotel;
    }

    public function setHotel(Hotel $hotel): self
    {
        $this->hotel = $hotel;

        return $this;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = strtolower(trim($locale));

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description !== null ? trim($description) : null;
        $this->updatedAtUtc = new \DateTimeImmutable();

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom !== null ? trim($nom) : null;
        $this->updatedAtUtc = new \DateTimeImmutable();

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): self
    {
        $this->adresse = $adresse !== null ? trim($adresse) : null;
        $this->updatedAtUtc = new \DateTimeImmutable();

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $ville !== null ? trim($ville) : null;
        $this->updatedAtUtc = new \DateTimeImmutable();

        return $this;
    }

    public function getUpdatedAtUtc(): ?\DateTimeImmutable
    {
        return $this->updatedAtUtc;
    }
}
