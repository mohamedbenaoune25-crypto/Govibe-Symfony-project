<?php

namespace App\Repository;

use App\Entity\ReservationSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReservationSession>
 */
class ReservationSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReservationSession::class);
    }

    public function countReservationsByUser(string $userRef): int
    {
        return $this->createQueryBuilder('r')
            ->select('COUNT(r.idReservation)')
            ->where('r.userRef = :user')
            ->setParameter('user', $userRef)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTotalParticipantsByUser(string $userRef): int
    {
        return $this->createQueryBuilder('r')
            ->select('SUM(r.nbPlaces)')
            ->where('r.userRef = :user')
            ->setParameter('user', $userRef)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    public function getMostReservedActivitiesByUser(string $userRef): array
    {
        return $this->createQueryBuilder('r')
            ->select('a.name as activityName, COUNT(r.idReservation) as totalCount')
            ->join('r.session', 's')
            ->join('s.activite', 'a')
            ->where('r.userRef = :user')
            ->setParameter('user', $userRef)
            ->groupBy('a.id')
            ->orderBy('totalCount', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }
}
