<?php

namespace App\Entity;

use App\Repository\MembreForumRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MembreForumRepository::class)]
#[ORM\Table(name: 'membre_forum')]
class MembreForum
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Forum::class)]
    #[ORM\JoinColumn(name: 'forum_id', referencedColumnName: 'forum_id', nullable: false, onDelete: 'CASCADE')]
    private ?Forum $forum = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Personne::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Personne $user = null;

    #[ORM\Column(name: 'date_adhesion', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $dateAdhesion = null;

    #[ORM\Column(name: 'status', length: 20, options: ['default' => 'PENDING'])]
    private ?string $status = 'PENDING';

    public function __construct()
    {
        $this->dateAdhesion = new \DateTime();
        $this->status = 'PENDING';
    }

    public function getForum(): ?Forum
    {
        return $this->forum;
    }

    public function setForum(?Forum $forum): self
    {
        $this->forum = $forum;
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

    public function getDateAdhesion(): ?\DateTimeInterface
    {
        return $this->dateAdhesion;
    }

    public function setDateAdhesion(?\DateTimeInterface $dateAdhesion): self
    {
        $this->dateAdhesion = $dateAdhesion;
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
}
