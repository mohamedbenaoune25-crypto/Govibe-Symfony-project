<?php

namespace App\Controller\Api;

use App\Entity\Voiture;
use App\Repository\VoitureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/ai/cars')]
class AiCarApiController extends AbstractController
{
    private const CITY_COORDS = [
        'tunis' => ['lat' => 36.8065, 'lng' => 10.1815],
        'sousse' => ['lat' => 35.8256, 'lng' => 10.6369],
        'sfax' => ['lat' => 34.7406, 'lng' => 10.7603],
        'nabeul' => ['lat' => 36.4561, 'lng' => 10.7376],
        'monastir' => ['lat' => 35.7643, 'lng' => 10.8113],
        'mahdia' => ['lat' => 35.5047, 'lng' => 11.0622],
        'bizerte' => ['lat' => 37.2744, 'lng' => 9.8739],
        'ariana' => ['lat' => 36.8665, 'lng' => 10.1647],
        'ben arous' => ['lat' => 36.7531, 'lng' => 10.2189],
    ];

    #[Route('/recommendations', name: 'app_api_ai_cars_recommendations', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function recommendations(
        Request $request,
        VoitureRepository $voitureRepository,
        HttpClientInterface $httpClient
    ): Response
    {
        $locationQuery = trim((string) $request->query->get('location', ''));
        $maxPrice = (float) $request->query->get('maxPrice', 0);
        $externalSourceUrl = trim((string) $request->query->get('externalSourceUrl', ''));

        $qb = $voitureRepository->createQueryBuilder('v')
            ->where('v.statut = :status')
            ->setParameter('status', 'DISPONIBLE');

        if ($maxPrice > 0) {
            $qb->andWhere('v.prixJour <= :maxPrice')
                ->setParameter('maxPrice', $maxPrice);
        }

        /** @var Voiture[] $cars */
        $cars = $qb->orderBy('v.prixJour', 'ASC')->getQuery()->getResult();

        $results = [];
        foreach ($cars as $car) {
            $score = $this->computeCarAiScore($car, $locationQuery, $maxPrice);

            if ($locationQuery !== '' && $score < 10) {
                continue;
            }

            $results[] = [
                'id' => $car->getIdVoiture(),
                'modele' => trim(((string) $car->getMarque()) . ' ' . ((string) $car->getModele())),
                'prix' => round((float) $car->getPrixJour(), 2),
                'agence' => (string) $car->getAdresseAgence(),
                'localisation' => $this->resolveCarLocation($car),
                'score' => round($score, 2),
                'source' => 'local-ranking',
                'rentable' => true,
            ];
        }

        $externalCount = 0;
        if ($externalSourceUrl !== '') {
            $externalCars = $this->readExternalCarsFromUrl($externalSourceUrl, $httpClient);
            foreach ($externalCars as $rawCar) {
                if (!is_array($rawCar)) {
                    continue;
                }

                $modele = trim($this->pickString($rawCar, ['modele', 'model', 'title']));
                $marque = trim($this->pickString($rawCar, ['marque', 'brand', 'make']));
                $agence = trim($this->pickString($rawCar, ['agence', 'agency', 'adresseAgence', 'address', 'localisation', 'location']));
                $prix = $this->pickFloat($rawCar, ['prix', 'prixJour', 'price', 'dailyPrice'], 0.0);

                if ($modele === '' && $marque === '') {
                    continue;
                }
                if ($agence === '') {
                    $agence = 'Source externe';
                }
                if ($prix <= 0) {
                    continue;
                }
                if ($maxPrice > 0 && $prix > $maxPrice) {
                    continue;
                }

                $coords = $this->extractCoordinates($rawCar, $agence);
                $score = $this->computeExternalCarScore(
                    $marque . ' ' . $modele,
                    $agence,
                    $prix,
                    $coords['lat'],
                    $coords['lng'],
                    $locationQuery,
                    $maxPrice
                );

                if ($locationQuery !== '' && $score < 10) {
                    continue;
                }

                $externalCount++;
                $results[] = [
                    'id' => 'ext-' . substr(md5(json_encode($rawCar)), 0, 10),
                    'modele' => trim(($marque . ' ' . $modele)),
                    'prix' => round($prix, 2),
                    'agence' => $agence,
                    'localisation' => $this->resolveLocationFromText($agence),
                    'score' => round($score, 2),
                    'source' => 'external-feed',
                    'rentable' => false,
                ];
            }
        }

        $rankedByHf = false;
        if ($locationQuery !== '' && count($results) > 1) {
            $hfResults = $this->rankCarsWithHuggingFace($results, $locationQuery, $maxPrice, $httpClient);
            if ($hfResults !== null) {
                $results = $hfResults;
                $rankedByHf = true;
            }
        }

        usort($results, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);

        return $this->json([
            'provider' => $rankedByHf ? 'huggingface' : 'local-fallback',
            'locationQuery' => $locationQuery,
            'maxPrice' => $maxPrice,
            'externalSourceUrl' => $externalSourceUrl,
            'externalCount' => $externalCount,
            'count' => count($results),
            'cars' => array_slice($results, 0, 12),
        ]);
    }

    private function pickString(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                return trim((string) $data[$key]);
            }
        }

        return '';
    }

    private function pickFloat(array $data, array $keys, float $default): float
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) {
                return (float) $data[$key];
            }
        }

        return $default;
    }

    private function extractCoordinates(array $rawCar, string $fallbackCityText): array
    {
        $lat = null;
        $lng = null;

        foreach (['lat', 'latitude'] as $key) {
            if (isset($rawCar[$key]) && is_numeric($rawCar[$key])) {
                $lat = (float) $rawCar[$key];
                break;
            }
        }

        foreach (['lng', 'longitude', 'lon'] as $key) {
            if (isset($rawCar[$key]) && is_numeric($rawCar[$key])) {
                $lng = (float) $rawCar[$key];
                break;
            }
        }

        if ($lat !== null && $lng !== null) {
            return ['lat' => $lat, 'lng' => $lng];
        }

        $normalized = mb_strtolower($fallbackCityText);
        foreach (self::CITY_COORDS as $city => $coords) {
            if (str_contains($normalized, $city)) {
                return $coords;
            }
        }

        return self::CITY_COORDS['tunis'];
    }

    private function computeCarAiScore(Voiture $car, string $locationQuery, float $maxPrice): float
    {
        $score = 20.0;
        $query = mb_strtolower(trim($locationQuery));
        $agency = mb_strtolower((string) $car->getAdresseAgence());
        $model = mb_strtolower(trim(((string) $car->getMarque()) . ' ' . ((string) $car->getModele())));

        if ($query !== '') {
            if (str_contains($agency, $query) || str_contains($model, $query)) {
                $score += 60.0;
            } else {
                $simAgency = 0.0;
                similar_text($query, $agency, $simAgency);
                $simModel = 0.0;
                similar_text($query, $model, $simModel);
                $score += max($simAgency, $simModel) * 0.25;
            }

            $cityMatchScore = $this->computeCityDistanceScore($car, $query);
            $score += $cityMatchScore;
        }

        $price = (float) $car->getPrixJour();
        if ($maxPrice > 0) {
            $affordability = max(0.0, min(1.0, 1 - ($price / $maxPrice)));
            $score += $affordability * 20.0;
        }

        return $score;
    }

    private function computeCityDistanceScore(Voiture $car, string $query): float
    {
        foreach (self::CITY_COORDS as $city => $coords) {
            if (!str_contains($query, $city)) {
                continue;
            }

            $lat = $car->getLatitude();
            $lng = $car->getLongitude();
            if ($lat === null || $lng === null) {
                return 0.0;
            }

            $distanceKm = $this->haversineDistanceKm((float) $lat, (float) $lng, $coords['lat'], $coords['lng']);
            return max(0.0, 40.0 - ($distanceKm * 0.6));
        }

        return 0.0;
    }

    private function resolveCarLocation(Voiture $car): string
    {
        return $this->resolveLocationFromText((string) $car->getAdresseAgence());
    }

    private function resolveLocationFromText(string $address): string
    {
        $normalizedAddress = mb_strtolower($address);

        foreach (array_keys(self::CITY_COORDS) as $city) {
            if (str_contains($normalizedAddress, $city)) {
                return ucfirst($city);
            }
        }

        return $address;
    }

    private function haversineDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    private function rankCarsWithHuggingFace(
        array $results,
        string $locationQuery,
        float $maxPrice,
        HttpClientInterface $httpClient
    ): ?array {
        $candidates = array_slice($results, 0, 20);
        if ($candidates === []) {
            return null;
        }

        $labelMap = [];
        $labels = [];
        foreach ($candidates as $car) {
            $label = sprintf(
                'id:%s | %s | %s | %.2f DT',
                (string) $car['id'],
                (string) $car['modele'],
                (string) $car['localisation'],
                (float) $car['prix']
            );
            $labels[] = $label;
            $labelMap[$label] = (string) $car['id'];
        }

        $query = 'Localisation: ' . $locationQuery;
        if ($maxPrice > 0) {
            $query .= ' | Budget max: ' . number_format($maxPrice, 2, '.', '') . ' DT/jour';
        }

        $headers = array_merge([
            'Content-Type' => 'application/json',
        ], $this->getHuggingFaceAuthHeaders());

        try {
            $response = $httpClient->request('POST', 'https://api-inference.huggingface.co/models/facebook/bart-large-mnli', [
                'headers' => $headers,
                'json' => [
                    'inputs' => $query,
                    'parameters' => [
                        'candidate_labels' => $labels,
                        'multi_label' => true,
                    ],
                ],
                'timeout' => 20,
            ]);

            $payload = $response->toArray(false);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($payload) || !isset($payload['labels'], $payload['scores']) || !is_array($payload['labels']) || !is_array($payload['scores'])) {
            return null;
        }

        $scoreById = [];
        foreach ($payload['labels'] as $index => $label) {
            if (!is_string($label) || !isset($labelMap[$label])) {
                continue;
            }

            $id = $labelMap[$label];
            $hfScore = isset($payload['scores'][$index]) && is_numeric($payload['scores'][$index])
                ? (float) $payload['scores'][$index]
                : 0.0;
            $scoreById[$id] = $hfScore;
        }

        foreach ($results as &$car) {
            $id = (string) $car['id'];
            if (isset($scoreById[$id])) {
                $car['score'] = round($car['score'] + ($scoreById[$id] * 100), 2);
                $car['source'] = 'huggingface';
            }
        }
        unset($car);

        return $results;
    }

    private function getHuggingFaceAuthHeaders(): array
    {
        $token = (string) ($_ENV['HUGGINGFACE_API_TOKEN'] ?? getenv('HUGGINGFACE_API_TOKEN') ?: '');
        if ($token === '') {
            return [];
        }

        return [
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    private function readExternalCarsFromUrl(string $sourceUrl, HttpClientInterface $httpClient): array
    {
        if (!$this->isPublicHttpUrl($sourceUrl)) {
            return [];
        }

        try {
            $response = $httpClient->request('GET', $sourceUrl, ['timeout' => 20]);
            $payload = $response->toArray(false);
        } catch (\Throwable) {
            return [];
        }

        $cars = $this->extractCarsListFromPayload($payload);
        return is_array($cars) ? $cars : [];
    }

    private function extractCarsListFromPayload(mixed $payload): ?array
    {
        if (is_array($payload) && isset($payload['record']['cars']) && is_array($payload['record']['cars'])) {
            return $payload['record']['cars'];
        }

        if (is_array($payload) && isset($payload['cars']) && is_array($payload['cars'])) {
            return $payload['cars'];
        }

        if (is_array($payload)) {
            return $payload;
        }

        return null;
    }

    private function isPublicHttpUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        return true;
    }

    private function computeExternalCarScore(
        string $modele,
        string $agence,
        float $prix,
        float $lat,
        float $lng,
        string $locationQuery,
        float $maxPrice
    ): float {
        $score = 20.0;
        $query = mb_strtolower(trim($locationQuery));
        $agencyNormalized = mb_strtolower($agence);
        $modelNormalized = mb_strtolower($modele);

        if ($query !== '') {
            if (str_contains($agencyNormalized, $query) || str_contains($modelNormalized, $query)) {
                $score += 60.0;
            } else {
                $simAgency = 0.0;
                similar_text($query, $agencyNormalized, $simAgency);
                $simModel = 0.0;
                similar_text($query, $modelNormalized, $simModel);
                $score += max($simAgency, $simModel) * 0.25;
            }

            foreach (self::CITY_COORDS as $city => $coords) {
                if (!str_contains($query, $city)) {
                    continue;
                }

                $distanceKm = $this->haversineDistanceKm($lat, $lng, $coords['lat'], $coords['lng']);
                $score += max(0.0, 40.0 - ($distanceKm * 0.6));
                break;
            }
        }

        if ($maxPrice > 0) {
            $affordability = max(0.0, min(1.0, 1 - ($prix / $maxPrice)));
            $score += $affordability * 20.0;
        }

        return $score;
    }
}
