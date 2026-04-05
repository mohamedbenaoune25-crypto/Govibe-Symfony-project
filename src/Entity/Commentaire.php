<?php

namespace App\Entity;

use App\Repository\CommentaireRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommentaireRepository::class)]
#[ORM\Table(name: 'commentaire')]
class Commentaire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'commentaire_id')]
    private ?int $commentaireId = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $contenu = null;

    #[ORM\Column(name: 'date_commentaire', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $dateCommentaire = null;

    #[ORM\Column(name: 'date_modification', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateModification = null;

    #[ORM\Column(length: 20, options: ['default' => 'publié'])]
    private ?string $statut = 'publié';

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(name: 'parent_id', nullable: true, options: ['default' => 0])]
    private ?int $parentId = 0;

    #[ORM\Column(nullable: true, options: ['default' => 0])]
    private ?int $likes = 0;

    #[ORM\Column(nullable: true, options: ['default' => 0])]
    private ?int $dislikes = 0;

    #[ORM\ManyToOne(targetEntity: Poste::class, inversedBy: 'commentaires')]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'post_id', nullable: false, onDelete: 'CASCADE')]
    private ?Poste $poste = null;

    #[ORM\ManyToOne(targetEntity: Personne::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Personne $user = null;

    public function __construct()
    {
        $this->dateCommentaire = new \DateTime();
        $this->dateCreation = new \DateTime();
    }

    public function getCommentaireId(): ?int
    {
        return $this->commentaireId;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): self
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getDateCommentaire(): ?\DateTimeInterface
    {
        return $this->dateCommentaire;
    }

    public function getDateModification(): ?\DateTimeInterface
    {
        return $this->dateModification;
    }

    public function setDateModification(?\DateTimeInterface $dateModification): self
    {
        $this->dateModification = $dateModification;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function getParentId(): ?int
    {
        return $this->parentId;
    }

    public function setParentId(?int $parentId): self
    {
        $this->parentId = $parentId;
        return $this;
    }

    public function getLikes(): ?int
    {
        return $this->likes;
    }

    public function setLikes(?int $likes): self
    {
        $this->likes = $likes;
        return $this;
    }

    public function getDislikes(): ?int
    {
        return $this->dislikes;
    }

    public function setDislikes(?int $dislikes): self
    {
        $this->dislikes = $dislikes;
        return $this;
    }

    public function getPoste(): ?Poste
    {
        return $this->poste;
    }

    public function setPoste(?Poste $poste): self
    {
        $this->poste = $poste;
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
