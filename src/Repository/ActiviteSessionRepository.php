<?php

namespace App\Repository;

use App\Entity\ActiviteSession;
use App\Entity\Personne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActiviteSession>
 */
class ActiviteSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActiviteSession::class);
    }

    /**
     * Returns ALL sessions sorted by activity name (admin use).
     * @return ActiviteSession[]
     */
    public function findAllSortedByActiviteName(): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.activite', 'a')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns only the sessions created by a specific user.
     * @return ActiviteSession[]
     */
    public function findByUser(Personne $user): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.activite', 'a')
            ->where('s.createdBy = :user')
            ->setParameter('user', $user)
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

