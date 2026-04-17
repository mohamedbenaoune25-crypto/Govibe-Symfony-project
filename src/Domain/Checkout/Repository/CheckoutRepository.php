<?php

namespace App\Domain\Checkout\Repository;

use App\Domain\Checkout\Entity\Checkout;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Checkout>
 */
class CheckoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Checkout::class);
    }
}
