<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherService
{
    private array $weatherByCityCache = [];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getCurrentWeatherForCity(string $city, string $locale = 'fr'): array
    {
        $city = trim($city);
        $locale = $this->normalizeLocale($locale);

        if ($city === '') {
            return $this->errorResponse('Ville introuvable.', $city);
        }

        $cacheKey = $locale . '|' . strtolower($city);
        if (isset($this->weatherByCityCache[$cacheKey])) {
            return $this->weatherByCityCache[$cacheKey];
        }

        try {
            $coordinates = $this->geocodeCity($city, $locale);
            if ($coordinates === null) {
                return $this->weatherByCityCache[$cacheKey] = $this->errorResponse('Aucune donnée météo disponible pour cette ville.', $city);
            }

            $forecast = $this->fetchCurrentForecast($coordinates['latitude'], $coordinates['longitude']);
            if ($forecast === null) {
                return $this->weatherByCityCache[$cacheKey] = $this->errorResponse('Aucune donnée météo disponible pour cette ville.', $city);
            }

            $meta = $this->mapWeatherCode((int) ($forecast['weather_code'] ?? 0));
            $forecastDays = $this->buildDailyForecast($forecast['daily'] ?? [], $locale);

            return $this->weatherByCityCache[$cacheKey] = [
                'success' => true,
                'city' => $coordinates['name'],
                'country' => $coordinates['country'],
                'latitude' => $coordinates['latitude'],
                'longitude' => $coordinates['longitude'],
                'temperature' => round((float) ($forecast['temperature_2m'] ?? 0), 1),
                'apparent_temperature' => round((float) ($forecast['apparent_temperature'] ?? 0), 1),
                'wind_speed' => round((float) ($forecast['wind_speed_10m'] ?? 0), 1),
                'weather_code' => (int) ($forecast['weather_code'] ?? 0),
                'label' => $meta['label'],
                'summary' => $meta['summary'],
                'icon' => $meta['icon'],
                'forecast' => $forecastDays,
                'source' => 'Open-Meteo',
            ];
        } catch (TransportExceptionInterface $exception) {
            $this->logger->warning('[WeatherService] Weather API request failed', [
                'city' => $city,
                'error' => $exception->getMessage(),
            ]);

            return $this->weatherByCityCache[$cacheKey] = $this->errorResponse('Le service météo est temporairement indisponible.', $city);
        } catch (\Throwable $exception) {
            $this->logger->error('[WeatherService] Unexpected weather API error', [
                'city' => $city,
                'error' => $exception->getMessage(),
            ]);

            return $this->weatherByCityCache[$cacheKey] = $this->errorResponse('Impossible de récupérer la météo.', $city);
        }
    }

    private function geocodeCity(string $city, string $locale): ?array
    {
        $response = $this->httpClient->request('GET', 'https://geocoding-api.open-meteo.com/v1/search', [
            'query' => [
                'name' => $city,
                'count' => 1,
                'language' => $locale,
                'format' => 'json',
            ],
            'timeout' => 3,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $data = $response->toArray(false);
        $result = $data['results'][0] ?? null;

        if (!is_array($result) || !isset($result['latitude'], $result['longitude'])) {
            return null;
        }

        return [
            'name' => (string) ($result['name'] ?? $city),
            'country' => (string) ($result['country'] ?? ''),
            'latitude' => (float) $result['latitude'],
            'longitude' => (float) $result['longitude'],
        ];
    }

    private function fetchCurrentForecast(float $latitude, float $longitude): ?array
    {
        $response = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
            'query' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => 'temperature_2m,apparent_temperature,weather_code,wind_speed_10m',
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
                'timezone' => 'auto',
            ],
            'timeout' => 3,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $data = $response->toArray(false);
        $current = $data['current'] ?? null;

        if (!is_array($current)) {
            return null;
        }

        $current['daily'] = is_array($data['daily'] ?? null) ? $data['daily'] : [];

        return $current;
    }

    /**
     * @param array<string, mixed> $daily
     * @return array<int, array<string, mixed>>
     */
    private function buildDailyForecast(array $daily, string $locale): array
    {
        $times = $daily['time'] ?? [];
        $weatherCodes = $daily['weather_code'] ?? [];
        $maxTemperatures = $daily['temperature_2m_max'] ?? [];
        $minTemperatures = $daily['temperature_2m_min'] ?? [];
        $rainProbabilities = $daily['precipitation_probability_max'] ?? [];

        if (!is_array($times)) {
            return [];
        }

        $forecast = [];
        foreach ($times as $index => $time) {
            if (!is_string($time)) {
                continue;
            }

            if (!isset($weatherCodes[$index], $maxTemperatures[$index], $minTemperatures[$index])) {
                continue;
            }

            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $time);
            if (!$date instanceof \DateTimeImmutable) {
                continue;
            }

            $meta = $this->mapWeatherCode((int) $weatherCodes[$index]);
            $forecast[] = [
                'date' => $time,
                'day_label' => $this->formatDayLabel($date, $locale),
                'temperature_max' => round((float) $maxTemperatures[$index], 1),
                'temperature_min' => round((float) $minTemperatures[$index], 1),
                'rain_probability' => isset($rainProbabilities[$index]) ? (int) $rainProbabilities[$index] : null,
                'label' => $meta['label'],
                'summary' => $meta['summary'],
                'icon' => $meta['icon'],
            ];

            if (count($forecast) >= 3) {
                break;
            }
        }

        return $forecast;
    }

    private function formatDayLabel(\DateTimeImmutable $date, string $locale): string
    {
        $locale = $this->normalizeLocale($locale);
        $dayIndex = (int) $date->format('N');

        $labels = [
            'fr' => ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
            'en' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'de' => ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
            'it' => ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'],
            'es' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            'ar' => ['الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت', 'الأحد'],
        ];

        return $labels[$locale][$dayIndex - 1] ?? $date->format('D');
    }

    private function mapWeatherCode(int $code): array
    {
        return match (true) {
            $code === 0 => ['label' => 'Ciel dégagé', 'summary' => 'Conditions ensoleillées', 'icon' => 'wb_sunny'],
            in_array($code, [1, 2], true) => ['label' => 'Partiellement nuageux', 'summary' => 'Temps agréable avec quelques nuages', 'icon' => 'partly_cloudy_day'],
            $code === 3 => ['label' => 'Nuageux', 'summary' => 'Ciel couvert', 'icon' => 'cloud'],
            in_array($code, [45, 48], true) => ['label' => 'Brouillard', 'summary' => 'Visibilité réduite', 'icon' => 'foggy'],
            in_array($code, [51, 53, 55, 56, 57], true) => ['label' => 'Bruine', 'summary' => 'Faibles précipitations', 'icon' => 'rainy'],
            in_array($code, [61, 63, 65, 80, 81, 82], true) => ['label' => 'Pluie', 'summary' => 'Précipitations en cours', 'icon' => 'rainy'],
            in_array($code, [66, 67], true) => ['label' => 'Pluie verglaçante', 'summary' => 'Précipitations glacées', 'icon' => 'ac_unit'],
            in_array($code, [71, 73, 75, 77, 85, 86], true) => ['label' => 'Neige', 'summary' => 'Conditions hivernales', 'icon' => 'ac_unit'],
            in_array($code, [95, 96, 99], true) => ['label' => 'Orage', 'summary' => 'Activité orageuse', 'icon' => 'thunderstorm'],
            default => ['label' => 'Météo variable', 'summary' => 'Conditions changeantes', 'icon' => 'cloud'],
        };
    }

    private function normalizeLocale(string $locale): string
    {
        $locale = strtolower(trim($locale));
        $short = substr($locale, 0, 2);

        return in_array($short, ['fr', 'en', 'de', 'it', 'es', 'ar'], true) ? $short : 'fr';
    }

    private function errorResponse(string $message, string $city): array
    {
        return [
            'success' => false,
            'city' => $city,
            'country' => '',
            'temperature' => null,
            'apparent_temperature' => null,
            'wind_speed' => null,
            'weather_code' => null,
            'label' => $message,
            'summary' => $message,
            'icon' => 'cloud_off',
            'forecast' => [],
            'source' => 'Open-Meteo',
        ];
    }
}