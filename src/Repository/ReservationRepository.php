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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    public function findAllSorted(string $sortBy = 'dateDebut', string $sortDir = 'DESC'): array
    {
        $validSortFields = ['dateDebut', 'dateFin', 'prixTotal', 'statut', 'createdAt'];
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        if (!in_array($sortBy, $validSortFields)) {
            $sortBy = 'dateDebut';
        }

        return $this->createQueryBuilder('r')
            ->orderBy('r.' . $sortBy, $sortDir)
            ->getQuery()
            ->getResult();
    }

    public function searchReservations(string $search = '', string $sortBy = 'dateDebut', string $sortDir = 'DESC'): array
    {
        $validSortFields = ['dateDebut', 'dateFin', 'prixTotal', 'statut', 'createdAt'];
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        if (!in_array($sortBy, $validSortFields)) {
            $sortBy = 'dateDebut';
        }

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
}
