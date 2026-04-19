<?php

namespace App\Service;

use App\Entity\Hotel;
use App\Entity\HotelDescriptionTranslation;
use App\Repository\HotelDescriptionTranslationRepository;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;

class HotelDescriptionTranslationService
{
    private const TARGET_LOCALES = ['en', 'de', 'it', 'es', 'ar'];
    private const TRANSLATABLE_FIELDS = ['nom', 'adresse', 'ville', 'description'];

    public function __construct(
        private readonly GoogleCloudTranslationService $googleCloudTranslationService,
        private readonly HotelDescriptionTranslationRepository $hotelDescriptionTranslationRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function translateAndStore(Hotel $hotel, string $sourceLocale = 'fr'): void
    {
        $sourceData = [
            'nom' => trim((string) $hotel->getNom()),
            'adresse' => trim((string) $hotel->getAdresse()),
            'ville' => trim((string) $hotel->getVille()),
            'description' => trim((string) $hotel->getDescription()),
        ];

        if ($this->allEmpty($sourceData)) {
            return;
        }

        try {
            foreach (self::TARGET_LOCALES as $targetLocale) {
                $existing = $this->hotelDescriptionTranslationRepository->findOneByHotelAndLocale($hotel, $targetLocale);
                if (!$existing instanceof HotelDescriptionTranslation) {
                    $existing = (new HotelDescriptionTranslation())
                        ->setHotel($hotel)
                        ->setLocale($targetLocale);
                    $this->entityManager->persist($existing);
                }

                $existing->setNom($this->translateValue($sourceData['nom'], $targetLocale, $sourceLocale));
                $existing->setAdresse($this->translateValue($sourceData['adresse'], $targetLocale, $sourceLocale));
                $existing->setVille($this->translateValue($sourceData['ville'], $targetLocale, $sourceLocale));
                $existing->setDescription($this->translateValue($sourceData['description'], $targetLocale, $sourceLocale));
            }

            $this->entityManager->flush();
        } catch (DbalException) {
            // Keep write paths operational even when migrations are not yet applied.
            return;
        }
    }

    public function resolveDescription(Hotel $hotel, string $locale): string
    {
        return $this->resolveField($hotel, $locale, 'description');
    }

    public function resolveName(Hotel $hotel, string $locale): string
    {
        return $this->resolveField($hotel, $locale, 'nom');
    }

    public function resolveAddress(Hotel $hotel, string $locale): string
    {
        return $this->resolveField($hotel, $locale, 'adresse');
    }

    public function resolveCity(Hotel $hotel, string $locale): string
    {
        return $this->resolveField($hotel, $locale, 'ville');
    }

    public function resolveField(Hotel $hotel, string $locale, string $field): string
    {
        $field = strtolower(trim($field));
        if (!in_array($field, self::TRANSLATABLE_FIELDS, true)) {
            return '';
        }

        $locale = strtolower(trim($locale));
        $fallback = $this->fallbackValue($hotel, $field);

        if ($locale === '' || $locale === 'fr') {
            return $fallback;
        }

        try {
            $translation = $this->hotelDescriptionTranslationRepository->findOneByHotelAndLocale($hotel, $locale);

            // Backfill legacy hotels that existed before translation storage was introduced.
            if (!$translation instanceof HotelDescriptionTranslation) {
                $this->translateAndStore($hotel);
                $translation = $this->hotelDescriptionTranslationRepository->findOneByHotelAndLocale($hotel, $locale);
            } elseif (trim($this->translatedFieldValue($translation, $field)) === '') {
                // Backfill partial legacy rows where only some translated columns are empty.
                $this->translateAndStore($hotel);
                $translation = $this->hotelDescriptionTranslationRepository->findOneByHotelAndLocale($hotel, $locale);
            }
        } catch (DbalException) {
            return $fallback;
        }

        if (!$translation instanceof HotelDescriptionTranslation) {
            return $fallback;
        }

        $translated = $this->translatedFieldValue($translation, $field);

        return trim($translated) !== '' ? $translated : $fallback;
    }

    private function translatedFieldValue(HotelDescriptionTranslation $translation, string $field): string
    {
        return match ($field) {
            'nom' => (string) ($translation->getNom() ?? ''),
            'adresse' => (string) ($translation->getAdresse() ?? ''),
            'ville' => (string) ($translation->getVille() ?? ''),
            default => (string) ($translation->getDescription() ?? ''),
        };
    }

    /**
     * @param array<string, string> $values
     */
    private function allEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if (trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function translateValue(string $sourceText, string $targetLocale, string $sourceLocale): ?string
    {
        if (trim($sourceText) === '') {
            return null;
        }

        $translated = $this->googleCloudTranslationService->translateText($sourceText, $targetLocale, $sourceLocale);
        if (!is_string($translated) || trim($translated) === '') {
            return null;
        }

        return $translated;
    }

    private function fallbackValue(Hotel $hotel, string $field): string
    {
        return match ($field) {
            'nom' => (string) ($hotel->getNom() ?? ''),
            'adresse' => (string) ($hotel->getAdresse() ?? ''),
            'ville' => (string) ($hotel->getVille() ?? ''),
            default => (string) ($hotel->getDescription() ?? ''),
        };
    }
}
