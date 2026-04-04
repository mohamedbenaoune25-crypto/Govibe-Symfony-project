<?php

namespace App\Repository;

use App\Entity\Hotel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Hotel>
 */
class HotelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Hotel::class);
    }

    /**
     * Find all hotels sorted by the given criteria
     */
    public function findAllSorted(string $sortBy = 'nom', string $sortDir = 'asc'): array
    {
        $validSortFields = ['nom', 'ville', 'budget', 'nombreEtoiles', 'createdAt'];
        $sortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'nom';
        $sortDir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        return $this->createQueryBuilder('h')
            ->orderBy('h.'.$sortBy, $sortDir)
            ->getQuery()
            ->getResult();
    }

    /**
     * Search hotels by name and sort them
     */
    public function searchHotels(string $search, string $sortBy = 'nom', string $sortDir = 'asc'): array
    {
        $validSortFields = ['nom', 'ville', 'budget', 'nombreEtoiles', 'createdAt'];
        $sortBy = in_array($sortBy, $validSortFields) ? $sortBy : 'nom';
        $sortDir = strtolower($sortDir) === 'desc' ? 'DESC' : 'ASC';

        return $this->createQueryBuilder('h')
            ->where('h.nom LIKE :search')
            ->orWhere('h.ville LIKE :search')
            ->orWhere('h.adresse LIKE :search')
            ->orWhere('h.description LIKE :search')
            ->setParameter('search', '%'.$search.'%')
            ->orderBy('h.'.$sortBy, $sortDir)
            ->getQuery()
            ->getResult();
    }
}
