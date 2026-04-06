<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    private const VALID_SORT_FIELDS = ['dateDebut', 'dateFin', 'prixTotal', 'statut', 'createdAt'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findAllSorted(string $sortBy = 'dateDebut', string $sortDir = 'DESC'): array
    {
        [$sortBy, $sortDir] = $this->normalizeSorting($sortBy, $sortDir);

        return $this->createQueryBuilder('r')
            ->orderBy('r.' . $sortBy, $sortDir)
            ->getQuery()
            ->getResult();
    }

    public function searchReservations(string $search = '', string $sortBy = 'dateDebut', string $sortDir = 'DESC'): array
    {
        [$sortBy, $sortDir] = $this->normalizeSorting($sortBy, $sortDir);

        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->leftJoin('r.chambre', 'c')
            ->leftJoin('r.hotel', 'h')
            ->orderBy('r.' . $sortBy, $sortDir);

        if (!empty($search)) {
            $qb->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR c.type LIKE :search OR h.nom LIKE :search OR r.statut LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    public function findByHotelSorted(int $hotelId, string $sortBy = 'dateDebut', string $sortDir = 'DESC'): array
    {
        [$sortBy, $sortDir] = $this->normalizeSorting($sortBy, $sortDir);

        return $this->createQueryBuilder('r')
            ->leftJoin('r.hotel', 'h')
            ->andWhere('h.id = :hotelId')
            ->setParameter('hotelId', $hotelId)
            ->orderBy('r.' . $sortBy, $sortDir)
            ->getQuery()
            ->getResult();
    }

    public function searchReservationsByHotel(int $hotelId, string $search = '', string $sortBy = 'dateDebut', string $sortDir = 'DESC'): array
    {
        [$sortBy, $sortDir] = $this->normalizeSorting($sortBy, $sortDir);

        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->leftJoin('r.chambre', 'c')
            ->leftJoin('r.hotel', 'h')
            ->andWhere('h.id = :hotelId')
            ->setParameter('hotelId', $hotelId)
            ->orderBy('r.' . $sortBy, $sortDir);

        if (!empty($search)) {
            $qb->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR c.type LIKE :search OR h.nom LIKE :search OR r.statut LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }

    private function normalizeSorting(string $sortBy, string $sortDir): array
    {
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        if (!in_array($sortBy, self::VALID_SORT_FIELDS, true)) {
            $sortBy = 'dateDebut';
        }

        return [$sortBy, $sortDir];
    }
}
