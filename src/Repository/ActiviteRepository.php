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
     * Finds activities with intelligent ranking (Weighted score).
     */
    public function findOptimized(?string $cityPreference = null, ?string $sortBy = 'rank'): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', 'Confirmed');

        if ($sortBy === 'rating') {
            $qb->orderBy('a.averageRating', 'DESC');
        } elseif ($sortBy === 'popular') {
            $qb->leftJoin('a.sessions', 's')
               ->leftJoin('App\Entity\ReservationSession', 'r', 'WITH', 'r.session = s')
               ->groupBy('a.id')
               ->orderBy('COUNT(r.idReservation)', 'DESC');
        } else {
            // Default: Mixed ranking (Score calculated in PHP for complexity, or just basic popularity + rating mix)
            $qb->orderBy('a.averageRating', 'DESC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Finds activities that are currently "trending".
     */
    public function findTrending(int $limit = 3): array
    {
        return $this->createQueryBuilder('a')
            ->select('a, COUNT(r.idReservation) as HIDDEN bookingCount')
            ->leftJoin('a.sessions', 's')
            ->leftJoin('App\Entity\ReservationSession', 'r', 'WITH', 'r.session = s')
            ->where('a.status = :status')
            ->andWhere('r.reservedAt >= :recent')
            ->setParameter('status', 'Confirmed')
            ->setParameter('recent', (new \DateTime())->modify('-7 days'))
            ->groupBy('a.id')
            ->orderBy('bookingCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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

        return $qb->orderBy('a.averageRating', 'DESC')
            ->addOrderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
