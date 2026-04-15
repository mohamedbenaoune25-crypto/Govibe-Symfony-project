<?php

namespace App\Repository;

use App\Entity\LoginAttempt;
use App\Entity\Personne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LoginAttempt>
 */
class LoginAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginAttempt::class);
    }

    /**
     * Count failed login attempts for a user since a given datetime.
     */
    public function countRecentFailed(Personne $user, \DateTimeInterface $since): int
    {
        return (int) $this->createQueryBuilder('la')
            ->select('COUNT(la.id)')
            ->where('la.user = :user')
            ->andWhere('la.success = false')
            ->andWhere('la.loginTime >= :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find the most recent successful login attempt for a user.
     */
    public function findLastSuccessful(Personne $user): ?LoginAttempt
    {
        return $this->createQueryBuilder('la')
            ->where('la.user = :user')
            ->andWhere('la.success = true')
            ->setParameter('user', $user)
            ->orderBy('la.loginTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all login attempts for a user, ordered by most recent.
     */
    public function findByUserRecent(Personne $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('la')
            ->where('la.user = :user')
            ->setParameter('user', $user)
            ->orderBy('la.loginTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
