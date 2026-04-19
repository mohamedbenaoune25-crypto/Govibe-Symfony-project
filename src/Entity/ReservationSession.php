<?php

namespace App\Entity;

use App\Repository\ReservationSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReservationSessionRepository::class)]
#[ORM\Table(name: 'reservation_session')]
class ReservationSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_reservation')]
    private ?int $idReservation = null;

    #[ORM\Column(name: 'nb_places', nullable: true, options: ['default' => 1])]
    private ?int $nbPlaces = 1;

    #[ORM\Column(name: 'reserved_at', type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeImmutable $reservedAt = null;

    #[ORM\Column(name: 'user_ref', length: 50, options: ['default' => 'USER001'])]
    private ?string $userRef = 'USER001';

    #[ORM\ManyToOne(targetEntity: ActiviteSession::class)]
    #[ORM\JoinColumn(name: 'session_id', referencedColumnName: 'id_session', nullable: false, onDelete: 'CASCADE')]
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

    public function setUserRef(string $userRef): self
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
}
