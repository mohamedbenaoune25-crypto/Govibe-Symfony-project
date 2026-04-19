<?php

namespace App\Entity;

use App\Repository\ActiviteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActiviteRepository::class)]
#[ORM\Table(name: 'activite')]
class Activite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(length: 150)]
    private ?string $localisation = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    private ?string $prix = '0.00';

    #[ORM\Column(length: 20, options: ['default' => 'Confirmed'])]
    private ?string $status = 'Confirmed';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tags = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $ambiance = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true, options: ['default' => '09:00:00'])]
    private ?\DateTimeInterface $openingTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true, options: ['default' => '18:00:00'])]
    private ?\DateTimeInterface $closingTime = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $bestMoment = 'afternoon'; // morning, afternoon, evening, night

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $weatherType = 'both'; // sunny, rainy, both

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 8, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 11, scale: 8, nullable: true)]
    private ?string $longitude = null;

    #[ORM\OneToMany(mappedBy: 'activite', targetEntity: ActiviteSession::class, cascade: ['remove'])]
    private Collection $sessions;

    #[ORM\OneToMany(mappedBy: 'activite', targetEntity: ActiviteReview::class, cascade: ['remove'])]
    private Collection $reviews;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, options: ['default' => '0.00'])]
    private ?string $averageRating = '0.00';

    #[ORM\Column(options: ['default' => 0])]
    private ?int $reviewCount = 0;

    public function __construct()
    {
        $this->sessions = new ArrayCollection();
        $this->reviews = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(string $localisation): self
    {
        $this->localisation = $localisation;
        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(string $prix): self
    {
        $this->prix = $prix;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    public function getAmbiance(): ?string
    {
        return $this->ambiance;
    }

    public function setAmbiance(?string $ambiance): self
    {
        $this->ambiance = $ambiance;
        return $this;
    }

    public function getOpeningTime(): ?\DateTimeInterface
    {
        return $this->openingTime;
    }

    public function setOpeningTime(?\DateTimeInterface $openingTime): self
    {
        $this->openingTime = $openingTime;
        return $this;
    }

    public function getClosingTime(): ?\DateTimeInterface
    {
        return $this->closingTime;
    }

    public function setClosingTime(?\DateTimeInterface $closingTime): self
    {
        $this->closingTime = $closingTime;
        return $this;
    }

    public function getBestMoment(): ?string
    {
        return $this->bestMoment;
    }

    public function setBestMoment(?string $bestMoment): self
    {
        $this->bestMoment = $bestMoment;
        return $this;
    }

    public function getWeatherType(): ?string
    {
        return $this->weatherType;
    }

    public function setWeatherType(?string $weatherType): self
    {
        $this->weatherType = $weatherType;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    /**
     * @return Collection<int, ActiviteReview>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(ActiviteReview $review): self
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setActivite($this);
            $this->updateRatingCache();
        }
        return $this;
    }

    public function removeReview(ActiviteReview $review): self
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getActivite() === $this) {
                $review->setActivite(null);
            }
            $this->updateRatingCache();
        }
        return $this;
    }

    public function getAverageRating(): ?string
    {
        return $this->averageRating;
    }

    public function getReviewCount(): ?int
    {
        return $this->reviewCount;
    }

    private function updateRatingCache(): void
    {
        $this->reviewCount = count($this->reviews);
        if ($this->reviewCount === 0) {
            $this->averageRating = '0.00';
            return;
        }

        $total = 0;
        foreach ($this->reviews as $review) {
            $total += $review->getRating();
        }
        $this->averageRating = (string) round($total / $this->reviewCount, 2);
    }

    /**
     * @return Collection<int, ActiviteSession>
     */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    public function addSession(ActiviteSession $session): self
    {
        if (!$this->sessions->contains($session)) {
            $this->sessions->add($session);
            $session->setActivite($this);
        }

        return $this;
    }

    public function removeSession(ActiviteSession $session): self
    {
        if ($this->sessions->removeElement($session)) {
            // set the owning side to null (unless already changed)
            if ($session->getActivite() === $this) {
                $session->setActivite(null);
            }
        }

        return $this;
    }
}
