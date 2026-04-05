<?php

namespace App\Entity;

use App\Repository\ReservationSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ReservationSessionRepository::class)]
#[ORM\Table(name: 'reservation_session')]
class ReservationSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_reservation')]
    private ?int $idReservation = null;

    #[ORM\Column(name: 'nb_places', nullable: true, options: ['default' => 1])]
    #[Assert\NotBlank(message: "Veuillez indiquer le nombre de places.")]
    #[Assert\Positive(message: "Le nombre de places doit être supérieur à 0.")]
    private ?int $nbPlaces = 1;

    #[ORM\Column(name: 'reserved_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $reservedAt = null;

    #[ORM\Column(name: 'user_ref', length: 50, options: ['default' => 'USER001'])]
    #[Assert\NotBlank(message: "La référence utilisateur est obligatoire.")]
    #[Assert\Length(min: 3, max: 20, minMessage: "La référence doit faire au moins {{ limit }} caractères.", maxMessage: "La référence ne peut pas dépasser {{ limit }} caractères.")]
    #[Assert\Regex(
        pattern: "/^[A-Za-z0-9_-]+$/",
        message: "La référence ne peut contenir que des lettres, chiffres, tirets et underscores."
    )]
    private ?string $userRef = 'USER001';

    #[ORM\ManyToOne(targetEntity: ActiviteSession::class)]
    #[ORM\JoinColumn(name: 'session_id', referencedColumnName: 'id_session', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotBlank(message: "La session est obligatoire.")]
    private ?ActiviteSession $session = null;

    public function __construct()
    {
        $this->reservedAt = new \DateTimeImmutable();
    }

    public function getIdReservation(): ?int
    {
        return $this->idReservation;
    }

    public function getNbPlaces(): ?int
    {
        return $this->nbPlaces;
    }

    public function setNbPlaces(?int $nbPlaces): self
    {
        $this->nbPlaces = $nbPlaces;
        return $this;
    }

    public function getReservedAt(): ?\DateTimeImmutable
    {
        return $this->reservedAt;
    }

    public function getUserRef(): ?string
    {
        return $this->userRef;
    }

    public function setUserRef(?string $userRef): self
    {
        $this->userRef = $userRef;
        return $this;
    }

    public function getSession(): ?ActiviteSession
    {
        return $this->session;
    }

    public function setSession(?ActiviteSession $session): self
    {
        $this->session = $session;
        return $this;
    }

    #[Assert\Callback]
    public function validateAvailability(ExecutionContextInterface $context, $payload): void
    {
        if ($this->session && $this->nbPlaces !== null && $this->nbPlaces > $this->session->getNbrPlacesRestant()) {
            $context->buildViolation(sprintf(
                'Désolé, il ne reste que %d place(s) disponible(s) pour cette session.',
                $this->session->getNbrPlacesRestant()
            ))
            ->atPath('nbPlaces')
            ->addViolation();
        }
    }
}
