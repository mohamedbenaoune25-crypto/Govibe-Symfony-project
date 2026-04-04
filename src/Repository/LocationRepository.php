<?php

namespace App\Repository;

use App\Entity\Location;
use App\Entity\Voiture;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Location>
 */
class LocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /**
     * Vérifier si une voiture est disponible pour la période donnée
     */
    public function isVoitureAvailable(Voiture $voiture, \DateTime $dateDebut, \DateTime $dateFin, ?Location $excludeLocation = null): bool
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.voiture = :voiture')
            ->andWhere('l.statut IN (:statuts)')
            ->andWhere('(:dateDebut <= l.dateFin AND :dateFin >= l.dateDebut)')
            ->setParameter('voiture', $voiture)
            ->setParameter('statuts', ['CONFIRMEE', 'EN_ATTENTE'])
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin);

        if ($excludeLocation !== null) {
            $qb->andWhere('l.id != :excludeId')
               ->setParameter('excludeId', $excludeLocation->getIdLocation());
        }

        return count($qb->getQuery()->getResult()) === 0;
    }

    /**
     * Récupérer les locations d'un utilisateur
     */
    public function findByUser($user)
    {
        return $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer les locations actives pour une voiture (confirmées ou en attente)
     */
    public function findActiveByVoiture(Voiture $voiture)
    {
        return $this->createQueryBuilder('l')
            ->where('l.voiture = :voiture')
            ->andWhere('l.statut IN (:statuts)')
            ->setParameter('voiture', $voiture)
            ->setParameter('statuts', ['CONFIRMEE', 'EN_ATTENTE'])
            ->orderBy('l.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculer le revenu total
     */
    public function calculateTotalRevenue(): float
    {
        $result = $this->createQueryBuilder('l')
            ->select('SUM(CAST(l.montantTotal AS DECIMAL)) as total')
            ->where('l.statut IN (:statuts)')
            ->setParameter('statuts', ['CONFIRMEE', 'TERMINEE'])
            ->getQuery()
            ->getSingleResult();

        return floatval($result['total'] ?? 0);
    }

    /**
     * Obtenir les statistiques par statut
     */
    public function getStatsByStatus(): array
    {
        $result = $this->createQueryBuilder('l')
            ->select('l.statut, COUNT(l.id) as count')
            ->groupBy('l.statut')
            ->getQuery()
            ->getResult();

        return $result;
    }
}

