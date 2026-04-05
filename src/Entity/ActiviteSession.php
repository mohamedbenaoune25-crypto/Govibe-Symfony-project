<?php

namespace App\Entity;

use App\Repository\ActiviteSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActiviteSessionRepository::class)]
#[ORM\Table(name: 'sessions')]
class ActiviteSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_session')]
    private ?int $idSession = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: "La date est obligatoire.")]
    #[Assert\GreaterThanOrEqual(
        value: "today",
        message: "La date ne peut pas être dans le passé."
    )]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotBlank(message: "L'heure est obligatoire.")]
    private ?\DateTimeInterface $heure = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "La capacité est obligatoire.")]
    #[Assert\Positive(message: "La capacité doit être un nombre positif.")]
    private ?int $capacite = null;

    #[ORM\Column(name: 'nbr_places_restant')]
    #[Assert\PositiveOrZero(message: "Le nombre de places restantes ne peut pas être négatif.")]
    private ?int $nbrPlacesRestant = null;

    #[ORM\ManyToOne(targetEntity: Activite::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(name: 'activite_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Activite $activite = null;

    public function getIdSession(): ?int
    {
        return $this->idSession;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getHeure(): ?\DateTimeInterface
    {
        return $this->heure;
    }

    public function setHeure(\DateTimeInterface $heure): self
    {
        $this->heure = $heure;
        return $this;
    }

    public function getCapacite(): ?int
    {
        return $this->capacite;
    }

    public function setCapacite(int $capacite): self
    {
        $this->capacite = $capacite;
        return $this;
    }

    public function getNbrPlacesRestant(): ?int
    {
        return $this->nbrPlacesRestant;
    }

    public function setNbrPlacesRestant(int $nbrPlacesRestant): self
    {
        $this->nbrPlacesRestant = $nbrPlacesRestant;
        return $this;
    }

    public function getActivite(): ?Activite
    {
        return $this->activite;
    }

    public function setActivite(?Activite $activite): self
    {
        $this->activite = $activite;
        return $this;
    }

    public function canReserve(int $numPlaces = 1): bool
    {
        return $this->nbrPlacesRestant >= $numPlaces;
    }

    public function reservePlaces(int $numPlaces = 1): self
    {
        if ($this->canReserve($numPlaces)) {
            $this->nbrPlacesRestant -= $numPlaces;
        }
        return $this;
    }
}
