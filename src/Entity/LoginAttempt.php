<?php

namespace App\Entity;

use App\Repository\LoginAttemptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LoginAttemptRepository::class)]
#[ORM\Table(name: 'login_attempts')]
class LoginAttempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'ip_address', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $device = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $country = null;

    #[ORM\Column(name: 'login_time', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $loginTime = null;

    #[ORM\Column(options: ['default' => false])]
    private ?bool $success = false;

    #[ORM\Column(name: 'risk_score', nullable: true, options: ['default' => 0])]
    private ?float $riskScore = 0;

    #[ORM\Column(name: 'auth_level', length: 20, options: ['default' => 'LOW'])]
    private ?string $authLevel = 'LOW';

    #[ORM\ManyToOne(targetEntity: Personne::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Personne $user = null;

    public function __construct()
    {
        $this->loginTime = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getDevice(): ?string
    {
        return $this->device;
    }

    public function setDevice(?string $device): self
    {
        $this->device = $device;
        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function getLoginTime(): ?\DateTimeInterface
    {
        return $this->loginTime;
    }

    public function isSuccess(): ?bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): self
    {
        $this->success = $success;
        return $this;
    }

    public function getRiskScore(): ?float
    {
        return $this->riskScore;
    }

    public function setRiskScore(?float $riskScore): self
    {
        $this->riskScore = $riskScore;
        return $this;
    }

    public function getAuthLevel(): ?string
    {
        return $this->authLevel;
    }

    public function setAuthLevel(?string $authLevel): self
    {
        $this->authLevel = $authLevel;
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
