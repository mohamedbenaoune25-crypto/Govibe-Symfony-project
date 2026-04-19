<?php

namespace App\Repository;

use App\Entity\ActiviteReview;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActiviteReview>
 *
 * @method ActiviteReview|null find($id, $lockMode = null, $lockVersion = null)
 * @method ActiviteReview|null findOneBy(array $criteria, array $orderBy = null)
 * @method ActiviteReview[]    findAll()
 * @method ActiviteReview[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ActiviteReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActiviteReview::class);
    }
}
