<?php

namespace App\Repository;

use App\Entity\ActiviteSession;
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
     * @return ActiviteSession[] Returns an array of ActiviteSession objects sorted by Activity name
     */
    public function findAllSortedByActiviteName(): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.activite', 'a')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
