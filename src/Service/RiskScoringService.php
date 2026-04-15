<?php

namespace App\Service;

use App\Entity\Personne;
use App\Repository\LoginAttemptRepository;
use App\Repository\UserSessionRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * AI-powered risk scoring service.
 * Calls the Python Flask API at localhost:5001/predict-risk.
 * Falls back to a static scoring engine if the API is unavailable.
 */
class RiskScoringService
{
    private const API_URL = 'http://localhost:5001/predict-risk';
    private const API_TIMEOUT = 3; // seconds

    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private LoginAttemptRepository $loginAttemptRepo;
    private UserSessionRepository $userSessionRepo;
    private DeviceDetectorService $deviceDetector;
    private GeoIPService $geoIPService;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        LoginAttemptRepository $loginAttemptRepo,
        UserSessionRepository $userSessionRepo,
        DeviceDetectorService $deviceDetector,
        GeoIPService $geoIPService
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->loginAttemptRepo = $loginAttemptRepo;
        $this->userSessionRepo = $userSessionRepo;
        $this->deviceDetector = $deviceDetector;
        $this->geoIPService = $geoIPService;
    }

    /**
     * Evaluate the risk of a login attempt.
     *
     * @return array{
     *     risk_probability: float,
     *     risk_prediction: int,
     *     source: string,
     *     features: array,
     *     auth_level: string
     * }
     */
    public function evaluateRisk(Personne $user, Request $request): array
    {
        $features = $this->extractFeatures($user, $request);

        $this->logger->info('[RiskScoring] Features extracted', $features);

        // Try AI API first
        $result = $this->callAIApi($features);

        if ($result === null) {
            // Fallback to static scoring
            $result = $this->staticFallback($features);
        }

        // Determine auth level
        $result['auth_level'] = $this->determineAuthLevel($result['risk_probability'], $user);
        $result['features'] = $features;

        $this->logger->info('[RiskScoring] Final result', [
            'user' => $user->getEmail(),
            'probability' => $result['risk_probability'],
            'auth_level' => $result['auth_level'],
            'source' => $result['source'],
        ]);

        return $result;
    }

    /**
     * Extract the 5 features for the ML model from the current context.
     */
    private function extractFeatures(Personne $user, Request $request): array
    {
        $ip = $request->getClientIp() ?? '127.0.0.1';
        $userAgent = $request->headers->get('User-Agent', '');
        $currentDevice = $this->deviceDetector->detect($userAgent);
        $geoData = $this->geoIPService->locate($ip);
        $currentCountry = $geoData['country'];

        // 1. Failed attempts in last 30 minutes
        $since = (new \DateTime())->modify('-30 minutes');
        $failedAttempts = $this->loginAttemptRepo->countRecentFailed($user, $since);

        // 2. New device? Compare with known devices
        $knownDevices = $this->userSessionRepo->findDistinctDevices($user);
        $newDevice = empty($knownDevices) ? 0 : (in_array($currentDevice, $knownDevices, true) ? 0 : 1);

        // 3. New country? Compare with known countries
        $knownCountries = $this->userSessionRepo->findDistinctCountries($user);
        $newCountry = empty($knownCountries) ? 0 : (in_array($currentCountry, $knownCountries, true) ? 0 : 1);

        // 4. Unusual time? (between 00:00 and 05:00)
        $currentHour = (int) (new \DateTime())->format('H');
        $unusualTime = ($currentHour >= 0 && $currentHour < 5) ? 1 : 0;

        // 5. Is admin?
        $isAdmin = ($user->getRole() === 'admin') ? 1 : 0;

        return [
            'failed_attempts' => min($failedAttempts, 5), // Cap at 5 for the model
            'new_device'      => $newDevice,
            'new_country'     => $newCountry,
            'unusual_time'    => $unusualTime,
            'is_admin'        => $isAdmin,
            '_ip'             => $ip,
            '_device'         => $currentDevice,
            '_country'        => $currentCountry,
            '_city'           => $geoData['city'],
        ];
    }

    /**
     * Call the Python AI Risk API.
     */
    private function callAIApi(array $features): ?array
    {
        try {
            $payload = [
                'failed_attempts' => $features['failed_attempts'],
                'new_device'      => $features['new_device'],
                'new_country'     => $features['new_country'],
                'unusual_time'    => $features['unusual_time'],
                'is_admin'        => $features['is_admin'],
            ];

            $response = $this->httpClient->request('POST', self::API_URL, [
                'json' => $payload,
                'timeout' => self::API_TIMEOUT,
            ]);

            $data = $response->toArray(false);

            if (isset($data['risk_probability'])) {
                $this->logger->info('[RiskScoring] AI API response', $data);
                return [
                    'risk_probability' => (float) $data['risk_probability'],
                    'risk_prediction'  => (int) ($data['risk_prediction'] ?? 0),
                    'source'           => 'ai',
                ];
            }

            $this->logger->warning('[RiskScoring] AI API returned unexpected response', $data);
            return null;

        } catch (\Throwable $e) {
            $this->logger->warning('[RiskScoring] AI API unavailable, using fallback', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Static scoring fallback when the AI API is down.
     * Uses a point-based system matching the user's specification.
     */
    private function staticFallback(array $features): array
    {
        $score = 0;

        // IP different / new device: +2
        if ($features['new_device'] === 1) {
            $score += 2;
        }

        // New country: +2
        if ($features['new_country'] === 1) {
            $score += 2;
        }

        // 3+ failed attempts: +3
        if ($features['failed_attempts'] >= 3) {
            $score += 3;
        }

        // Unusual time: +1
        if ($features['unusual_time'] === 1) {
            $score += 1;
        }

        // Is admin: +1
        if ($features['is_admin'] === 1) {
            $score += 1;
        }

        // Max possible score = 9
        // Map to probability: score/9 scaled to 0-1 range
        // But use threshold: score >= 4 → suspicious (prob > 0.70)
        if ($score >= 4) {
            $probability = 0.70 + (($score - 4) / 10); // 0.70 to 1.0
        } elseif ($score >= 2) {
            $probability = 0.30 + (($score - 2) * 0.20); // 0.30 to 0.69
        } else {
            $probability = $score * 0.15; // 0.0 to 0.29
        }

        $probability = min(1.0, max(0.0, $probability));

        $this->logger->info('[RiskScoring] Static fallback result', [
            'score' => $score,
            'probability' => $probability,
        ]);

        return [
            'risk_probability' => round($probability, 6),
            'risk_prediction'  => $probability >= 0.50 ? 1 : 0,
            'source'           => 'fallback',
        ];
    }

    /**
     * Determine authentication level based on risk probability and user role.
     */
    private function determineAuthLevel(float $probability, Personne $user): string
    {
        $isAdmin = ($user->getRole() === 'admin');

        // Admins always get at least HIGH
        if ($isAdmin) {
            if ($probability >= 0.70) {
                return 'VERY_HIGH';
            }
            return 'HIGH';
        }

        // Regular users
        if ($probability < 0.30) {
            return 'LOW';
        }

        if ($probability < 0.70) {
            return 'HIGH';
        }

        return 'VERY_HIGH';
    }
}
