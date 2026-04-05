<?php

namespace App\Entity;

use App\Repository\ActiviteSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
    #[Assert\GreaterThanOrEqual("today", message: "La date de la session ne peut pas être dans le passé.")]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotBlank(message: "L'heure est obligatoire.")]
    private ?\DateTimeInterface $heure = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "La capacité est obligatoire.")]
    #[Assert\Positive(message: "La capacité doit être une valeur positive.")]
    #[Assert\Range(min: 1, max: 500, notInRangeMessage: "La capacité doit être comprise entre {{ min }} et {{ max }} places.")]
    private ?int $capacite = null;

    #[ORM\Column(name: 'nbr_places_restant')]
    #[Assert\NotNull(message: "Le nombre de places restantes est obligatoire.")]
    #[Assert\GreaterThanOrEqual(0, message: "Les places restantes ne peuvent pas être négatives.")]
    #[Assert\Expression(
        "this.getNbrPlacesRestant() <= this.getCapacite()",
        message: "Les places restantes ne peuvent pas dépasser la capacité totale."
    )]
    private ?int $nbrPlacesRestant = null;

    #[ORM\ManyToOne(targetEntity: Activite::class, inversedBy: 'sessions')]
    #[ORM\JoinColumn(name: 'activite_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Activite $activite = null;

    #[ORM\OneToMany(mappedBy: 'session', targetEntity: ReservationSession::class, cascade: ['remove'])]
    private Collection $reservationSessions;

    public function __construct()
    {
        $this->reservationSessions = new ArrayCollection();
    }

    public function getIdSession(): ?int
    {
        return $this->idSession;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getHeure(): ?\DateTimeInterface
    {
        return $this->heure;
    }

    public function setHeure(?\DateTimeInterface $heure): self
    {
        $this->heure = $heure;
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

    public function getNbrPlacesRestant(): ?int
    {
        return $this->nbrPlacesRestant;
    }

    public function setNbrPlacesRestant(?int $nbrPlacesRestant): self
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

    /**
     * @return Collection<int, ReservationSession>
     */
    public function getReservationSessions(): Collection
    {
        return $this->reservationSessions;
    }

    public function addReservationSession(ReservationSession $reservationSession): self
    {
        if (!$this->reservationSessions->contains($reservationSession)) {
            $this->reservationSessions->add($reservationSession);
            $reservationSession->setSession($this);
        }
        return $this;
    }

    public function removeReservationSession(ReservationSession $reservationSession): self
    {
        if ($this->reservationSessions->removeElement($reservationSession)) {
            if ($reservationSession->getSession() === $this) {
                $reservationSession->setSession(null);
            }
        }
        return $this;
    }

    #[Assert\Callback]
    public function validatePlaces(ExecutionContextInterface $context, $payload): void
    {
        if ($this->nbrPlacesRestant !== null && $this->capacite !== null && $this->nbrPlacesRestant > $this->capacite) {
            $context->buildViolation('Le nombre de places restantes ne peut pas dépasser la capacité totale.')
                ->atPath('nbrPlacesRestant')
                ->addViolation();
        }
    }
}
