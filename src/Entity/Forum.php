<?php

namespace App\Entity;

use App\Repository\ForumRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ForumRepository::class)]
#[ORM\Table(name: 'forum')]
class Forum
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'forum_id')]
    private ?int $forumId = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(name: 'post_count', nullable: true, options: ['default' => 0])]
    private ?int $postCount = 0;

    #[ORM\Column(name: 'nbr_members', nullable: true, options: ['default' => 0])]
    private ?int $nbrMembers = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'date_creation', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(name: 'is_private', nullable: true, options: ['default' => false])]
    private ?bool $isPrivate = false;

    #[ORM\ManyToOne(targetEntity: Personne::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Personne $createdBy = null;

    #[ORM\OneToMany(mappedBy: 'forum', targetEntity: Poste::class, cascade: ['remove'])]
    private Collection $postes;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
        $this->postes = new ArrayCollection();
    }

    public function getForumId(): ?int
    {
        return $this->forumId;
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

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getPostCount(): ?int
    {
        return $this->postCount;
    }

    public function setPostCount(?int $postCount): self
    {
        $this->postCount = $postCount;
        return $this;
    }

    public function getNbrMembers(): ?int
    {
        return $this->nbrMembers;
    }

    public function setNbrMembers(?int $nbrMembers): self
    {
        $this->nbrMembers = $nbrMembers;
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

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function isPrivate(): ?bool
    {
        return $this->isPrivate;
    }

    public function setIsPrivate(?bool $isPrivate): self
    {
        $this->isPrivate = $isPrivate;
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

    /**
     * @return Collection<int, Poste>
     */
    public function getPostes(): Collection
    {
        return $this->postes;
    }

    public function addPoste(Poste $poste): self
    {
        if (!$this->postes->contains($poste)) {
            $this->postes->add($poste);
            $poste->setForum($this);
        }

        return $this;
    }

    public function removePoste(Poste $poste): self
    {
        if ($this->postes->removeElement($poste)) {
            // set the owning side to null (unless already changed)
            if ($poste->getForum() === $this) {
                $poste->setForum(null);
            }
        }

        return $this;
    }
}
