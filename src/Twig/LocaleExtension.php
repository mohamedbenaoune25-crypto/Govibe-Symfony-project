<?php

namespace App\Twig;

use App\Entity\Hotel;
use App\Service\GoogleCloudTranslationService;
use App\Service\HotelDescriptionTranslationService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class LocaleExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly HotelDescriptionTranslationService $hotelDescriptionTranslationService,
        private readonly GoogleCloudTranslationService $googleCloudTranslationService
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('current_locale', [$this, 'currentLocale']),
            new TwigFunction('supported_locales', [$this, 'supportedLocales']),
            new TwigFunction('translated_hotel_description', [$this, 'translatedHotelDescription']),
            new TwigFunction('translated_hotel_value', [$this, 'translatedHotelValue']),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('auto_translate', [$this, 'autoTranslate']),
        ];
    }

    public function currentLocale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?: 'fr';
    }

    /**
     * @return array<int, array{code: string, label: string}>
     */
    public function supportedLocales(): array
    {
        return [
            ['code' => 'fr', 'label' => 'Francais'],
            ['code' => 'en', 'label' => 'English'],
            ['code' => 'ar', 'label' => 'العربية'],
            ['code' => 'de', 'label' => 'Deutsch'],
            ['code' => 'it', 'label' => 'Italiano'],
            ['code' => 'es', 'label' => 'Espanol'],
        ];
    }

    public function autoTranslate(mixed $value, ?string $sourceLocale = null): string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';
        if ($text === '') {
            return '';
        }

        $targetLocale = $this->currentLocale();
        if ($targetLocale === 'fr') {
            return $text;
        }

        $translated = $this->googleCloudTranslationService->translateText($text, $targetLocale, $sourceLocale);

        return is_string($translated) && trim($translated) !== '' ? $translated : $text;
    }

    public function translatedHotelDescription(Hotel $hotel): string
    {
        return $this->hotelDescriptionTranslationService->resolveDescription($hotel, $this->currentLocale());
    }

    public function translatedHotelValue(Hotel $hotel, string $field): string
    {
        return $this->hotelDescriptionTranslationService->resolveField($hotel, $this->currentLocale(), $field);
    }
}
