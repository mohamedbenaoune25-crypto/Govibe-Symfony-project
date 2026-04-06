<?php

namespace App\Repository;

use App\Entity\Chambre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Chambre>
 */
class ChambreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Chambre::class);
    }

    public function findAllSorted(string $sortBy = 'type', string $sortDir = 'ASC'): array
    {
        $validSortFields = ['type', 'capacite', 'prixStandard', 'prixHauteSaison', 'prixBasseSaison', 'createdAt'];
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        if (!in_array($sortBy, $validSortFields)) {
            $sortBy = 'type';
        }

        return $this->createQueryBuilder('c')
            ->orderBy('c.' . $sortBy, $sortDir)
            ->getQuery()
            ->getResult();
    }

    public function searchChambres(string $search = '', string $sortBy = 'type', string $sortDir = 'ASC'): array
    {
        $validSortFields = ['type', 'capacite', 'prixStandard', 'prixHauteSaison', 'prixBasseSaison', 'createdAt'];
        $sortDir = strtoupper($sortDir) === 'DESC' ? 'DESC' : 'ASC';

        if (!in_array($sortBy, $validSortFields)) {
            $sortBy = 'type';
        }

        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.hotel', 'h')
            ->orderBy('c.' . $sortBy, $sortDir);

        if (!empty($search)) {
            $qb->andWhere('c.type LIKE :search OR h.nom LIKE :search OR c.equipements LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
