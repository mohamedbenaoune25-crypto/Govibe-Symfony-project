<?php

namespace App\Entity;

use App\Repository\CheckoutRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CheckoutRepository::class)]
#[ORM\Table(name: 'checkout')]
class Checkout
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'checkout_id')]
    private ?int $checkoutId = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $reservationDate = null;

    #[ORM\Column(name: 'passenger_nbr')]
    private ?int $passengerNbr = null;

    #[ORM\Column(name: 'status_reservation', length: 255)]
    private ?string $statusReservation = null;

    #[ORM\Column(name: 'total_prix')]
    private ?int $totalPrix = null;

    #[ORM\Column(name: 'passenger_name', length: 255, nullable: true)]
    private ?string $passengerName = null;

    #[ORM\Column(name: 'passenger_email', length: 255, nullable: true)]
    private ?string $passengerEmail = null;

    #[ORM\Column(name: 'passenger_phone', length: 50, nullable: true)]
    private ?string $passengerPhone = null;

    #[ORM\Column(name: 'payment_method', length: 50, options: ['default' => 'CREDIT_CARD'])]
    private ?string $paymentMethod = 'CREDIT_CARD';

    #[ORM\Column(name: 'seat_preference', length: 20, options: ['default' => 'WINDOW'])]
    private ?string $seatPreference = 'WINDOW';

    #[ORM\Column(name: 'travel_class', length: 20, options: ['default' => 'Economy'])]
    private ?string $travelClass = 'Economy';

    #[ORM\ManyToOne(targetEntity: Vol::class)]
    #[ORM\JoinColumn(name: 'flight_id', referencedColumnName: 'flight_id', nullable: false, onDelete: 'CASCADE')]
    private ?Vol $flight = null;

    #[ORM\ManyToOne(targetEntity: Personne::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Personne $user = null;

    public function getCheckoutId(): ?int
    {
        return $this->checkoutId;
    }

    public function getReservationDate(): ?\DateTimeInterface
    {
        return $this->reservationDate;
    }

    public function setReservationDate(\DateTimeInterface $reservationDate): self
    {
        $this->reservationDate = $reservationDate;
        return $this;
    }

    public function getPassengerNbr(): ?int
    {
        return $this->passengerNbr;
    }

    public function setPassengerNbr(int $passengerNbr): self
    {
        $this->passengerNbr = $passengerNbr;
        return $this;
    }

    public function getStatusReservation(): ?string
    {
        return $this->statusReservation;
    }

    public function setStatusReservation(string $statusReservation): self
    {
        $this->statusReservation = $statusReservation;
        return $this;
    }

    public function getTotalPrix(): ?int
    {
        return $this->totalPrix;
    }

    public function setTotalPrix(int $totalPrix): self
    {
        $this->totalPrix = $totalPrix;
        return $this;
    }

    public function getPassengerName(): ?string
    {
        return $this->passengerName;
    }

    public function setPassengerName(?string $passengerName): self
    {
        $this->passengerName = $passengerName;
        return $this;
    }

    public function getPassengerEmail(): ?string
    {
        return $this->passengerEmail;
    }

    public function setPassengerEmail(?string $passengerEmail): self
    {
        $this->passengerEmail = $passengerEmail;
        return $this;
    }

    public function getPassengerPhone(): ?string
    {
        return $this->passengerPhone;
    }

    public function setPassengerPhone(?string $passengerPhone): self
    {
        $this->passengerPhone = $passengerPhone;
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getSeatPreference(): ?string
    {
        return $this->seatPreference;
    }

    public function setSeatPreference(?string $seatPreference): self
    {
        $this->seatPreference = $seatPreference;
        return $this;
    }

    public function getTravelClass(): ?string
    {
        return $this->travelClass;
    }

    public function setTravelClass(?string $travelClass): self
    {
        $this->travelClass = $travelClass;
        return $this;
    }

    public function getFlight(): ?Vol
    {
        return $this->flight;
    }

    public function setFlight(?Vol $flight): self
    {
        $this->flight = $flight;
        return $this;
    }

    public function getUser(): ?Personne
    {
        return $this->user;
    }

    public function setUser(?Personne $user): self
    {
        $this->user = $user;
        return $this;
    }
}
