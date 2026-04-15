<?php

namespace App\Repository;

use App\Entity\OtpCode;
use App\Entity\Personne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OtpCode>
 */
class OtpCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OtpCode::class);
    }

    /**
     * Find the latest valid (not used, not expired) OTP for a user.
     */
    public function findLatestValidOtp(Personne $user): ?OtpCode
    {
        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->andWhere('o.used = false')
            ->andWhere('o.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTime())
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Invalidate all unused OTPs for a user (mark as used).
     */
    public function invalidateAllForUser(Personne $user): void
    {
        $this->createQueryBuilder('o')
            ->update()
            ->set('o.used', 'true')
            ->where('o.user = :user')
            ->andWhere('o.used = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
