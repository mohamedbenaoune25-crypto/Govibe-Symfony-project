<?php

namespace App\Entity;

use App\Repository\ActiviteSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActiviteSessionRepository::class)]
#[ORM\Table(name: 'sessions')]
class ActiviteSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_session')]
    private ?int $idSession = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $heure = null;

    #[ORM\Column]
    private ?int $capacite = null;

    #[ORM\Column(name: 'nbr_places_restant')]
    private ?int $nbrPlacesRestant = null;

    #[ORM\ManyToOne(targetEntity: Activite::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(name: 'activite_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Activite $activite = null;

    #[ORM\ManyToOne(targetEntity: Personne::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Personne $createdBy = null;

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

    public function getCreatedBy(): ?Personne
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?Personne $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function canReserve(int $nbrPlaces): bool
    {
        return $this->nbrPlacesRestant !== null && $this->nbrPlacesRestant >= $nbrPlaces;
    }

    public function reservePlaces(int $nbPlaces): self
    {
        if (!$this->canReserve($nbPlaces)) {
            throw new \LogicException('Not enough places remaining.');
        }

        $this->nbrPlacesRestant -= $nbPlaces;
        return $this;
    }

    public function adjustPlaces(int $oldNb, int $newNb): self
    {
        $diff = $newNb - $oldNb;
        if ($diff > 0 && !$this->canReserve($diff)) {
            throw new \LogicException('Not enough places remaining for adjustment.');
        }

        $this->nbrPlacesRestant -= $diff;
        return $this;
    }
}
