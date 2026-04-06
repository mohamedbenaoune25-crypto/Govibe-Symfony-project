<?php

namespace App\Repository;

use App\Entity\Forum;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Forum>
 */
class ForumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Forum::class);
    }

    /**
     * @return Forum[]
     */
    public function findFilteredForums(?string $sort = null, ?\App\Entity\Personne $user = null): array
    {
        $qb = $this->createQueryBuilder('f');

        switch ($sort) {
            case 'mine':
                if ($user) {
                    $qb->andWhere('f.createdBy = :currentUser')
                       ->setParameter('currentUser', $user);
                }
                $qb->orderBy('f.dateCreation', 'DESC');
                break;
            case 'popular':
                // Popularity based on sum of members and posts
                $qb->addSelect('(f.nbrMembers + f.postCount) as HIDDEN score')
                   ->orderBy('score', 'DESC');
                break;
            case 'newest':
                $qb->orderBy('f.dateCreation', 'DESC');
                break;
            case 'private':
                $qb->andWhere('f.isPrivate = :isPrivate')
                   ->setParameter('isPrivate', true)
                   ->orderBy('f.dateCreation', 'DESC');
                break;
            default:
                $qb->orderBy('f.dateCreation', 'DESC');
                break;
        }

        return $qb->getQuery()->getResult();
    }

    public function getTotalMemberCount(): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('SUM(f.nbrMembers)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
