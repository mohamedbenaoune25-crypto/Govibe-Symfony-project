<?php

namespace App\Entity;

use App\Repository\VolRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VolRepository::class)]
#[ORM\Table(name: 'vol')]
class Vol
{
    #[ORM\Id]
    #[ORM\Column(name: 'flight_id', length: 50)]
    private ?string $flightId = null;

    #[ORM\Column(length: 100)]
    private ?string $departureAirport = null;

    #[ORM\Column(length: 100)]
    private ?string $destination = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $departureTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $arrivalTime = null;

    #[ORM\Column(name: 'classe_chaise', length: 255)]
    private ?string $classeChaise = null;

    #[ORM\Column(length: 100)]
    private ?string $airline = null;

    #[ORM\Column]
    private ?int $prix = null;

    #[ORM\Column(name: 'available_seats')]
    private ?int $availableSeats = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    public function getFlightId(): ?string
    {
        return $this->flightId;
    }

    public function setFlightId(string $flightId): self
    {
        $this->flightId = $flightId;
        return $this;
    }

    public function getDepartureAirport(): ?string
    {
        return $this->departureAirport;
    }

    public function setDepartureAirport(string $departureAirport): self
    {
        $this->departureAirport = $departureAirport;
        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(string $destination): self
    {
        $this->destination = $destination;
        return $this;
    }

    public function getDepartureTime(): ?\DateTimeInterface
    {
        return $this->departureTime;
    }

    public function setDepartureTime(\DateTimeInterface $departureTime): self
    {
        $this->departureTime = $departureTime;
        return $this;
    }

    public function getArrivalTime(): ?\DateTimeInterface
    {
        return $this->arrivalTime;
    }

    public function setArrivalTime(\DateTimeInterface $arrivalTime): self
    {
        $this->arrivalTime = $arrivalTime;
        return $this;
    }

    public function getClasseChaise(): ?string
    {
        return $this->classeChaise;
    }

    public function setClasseChaise(string $classeChaise): self
    {
        $this->classeChaise = $classeChaise;
        return $this;
    }

    public function getAirline(): ?string
    {
        return $this->airline;
    }

    public function setAirline(string $airline): self
    {
        $this->airline = $airline;
        return $this;
    }

    public function getPrix(): ?int
    {
        return $this->prix;
    }

    public function setPrix(int $prix): self
    {
        $this->prix = $prix;
        return $this;
    }

    public function getAvailableSeats(): ?int
    {
        return $this->availableSeats;
    }

    public function setAvailableSeats(int $availableSeats): self
    {
        $this->availableSeats = $availableSeats;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }
}
