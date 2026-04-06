<?php

namespace App\Repository;

use App\Entity\Poste;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Poste>
 */
class PosteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Poste::class);
    }

    /**
     * @return Poste[]
     */
    public function searchAndSort(?string $query, ?string $sort, ?bool $globalOnly = true, ?\App\Entity\Forum $forum = null, ?\App\Entity\Personne $user = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.commentaires', 'c')
            ->addSelect('COUNT(c) as HIDDEN commentCount')
            ->groupBy('p.postId');
        
        if ($sort === 'mine' && $user) {
            $qb->andWhere('p.user = :currentUser')
               ->setParameter('currentUser', $user);
        }

        if ($forum) {
            $qb->andWhere('p.forum = :forum')
               ->setParameter('forum', $forum);
        } elseif ($globalOnly) {
            $qb->andWhere('p.forum IS NULL');
        }

        if ($query) {
            $qb->andWhere('p.contenu LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        if ($sort === 'popular') {
            $qb->orderBy('p.likes', 'DESC')
               ->addOrderBy('commentCount', 'DESC');
        } else {
            $qb->orderBy('p.dateCreation', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }
}
