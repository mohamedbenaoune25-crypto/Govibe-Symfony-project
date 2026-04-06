<?php

namespace App\Repository;

use App\Entity\Activite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activite>
 */
class ActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activite::class);
    }

    /**
     * @return Activite[] Returns an array of Activite objects matching the search query and status
     */
    public function findBySearch(string $query, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.name LIKE :query')
            ->setParameter('query', '%' . $query . '%');

        if ($status) {
            $qb->andWhere('a.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
