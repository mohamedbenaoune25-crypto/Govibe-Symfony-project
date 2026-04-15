<?php

namespace App\Repository;

use App\Entity\Hotel;
use App\Entity\HotelDescriptionTranslation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HotelDescriptionTranslation>
 */
class HotelDescriptionTranslationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HotelDescriptionTranslation::class);
    }

    public function findOneByHotelAndLocale(Hotel $hotel, string $locale): ?HotelDescriptionTranslation
    {
        return $this->findOneBy([
            'hotel' => $hotel,
            'locale' => strtolower($locale),
        ]);
    }
}
