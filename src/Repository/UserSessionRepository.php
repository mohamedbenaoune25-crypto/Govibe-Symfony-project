<?php

namespace App\Repository;

use App\Entity\UserSession;
use App\Entity\Personne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSession>
 */
class UserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSession::class);
    }

    /**
     * Find all active sessions for a user, ordered by most recent.
     */
    public function findActiveSessions(Personne $user): array
    {
        return $this->createQueryBuilder('us')
            ->where('us.user = :user')
            ->andWhere('us.isActive = true')
            ->setParameter('user', $user)
            ->orderBy('us.lastActivity', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get distinct countries from a user's previous sessions.
     */
    public function findDistinctCountries(Personne $user): array
    {
        $results = $this->createQueryBuilder('us')
            ->select('DISTINCT us.country')
            ->where('us.user = :user')
            ->andWhere('us.country IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'country');
    }

    /**
     * Get distinct device names from a user's previous sessions.
     */
    public function findDistinctDevices(Personne $user): array
    {
        $results = $this->createQueryBuilder('us')
            ->select('DISTINCT us.deviceName')
            ->where('us.user = :user')
            ->andWhere('us.deviceName IS NOT NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getScalarResult();

        return array_column($results, 'deviceName');
    }

    /**
     * Deactivate all sessions for a user except the given one.
     */
    public function deactivateAllExcept(Personne $user, ?string $exceptSessionId = null): int
    {
        $qb = $this->createQueryBuilder('us')
            ->update()
            ->set('us.isActive', 'false')
            ->where('us.user = :user')
            ->andWhere('us.isActive = true')
            ->setParameter('user', $user);

        if ($exceptSessionId) {
            $qb->andWhere('us.id != :exceptId')
               ->setParameter('exceptId', $exceptSessionId);
        }

        return $qb->getQuery()->execute();
    }
}
