<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * IP Geolocation service using the free ip-api.com API.
 */
class GeoIPService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Geolocate an IP address.
     *
     * @return array{country: string, city: string}
     */
    public function locate(string $ip): array
    {
        $default = ['country' => 'Local', 'city' => 'Local'];

        // Private/localhost IPs can't be geolocated
        if ($this->isPrivateIP($ip)) {
            return $default;
        }

        try {
            $response = $this->httpClient->request('GET', 'http://ip-api.com/json/' . $ip, [
                'timeout' => 3,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            $data = $response->toArray(false);

            if (($data['status'] ?? '') === 'success') {
                return [
                    'country' => $data['country'] ?? 'Unknown',
                    'city'    => $data['city'] ?? 'Unknown',
                ];
            }

            $this->logger->warning('[GeoIP] API returned non-success status', ['ip' => $ip, 'data' => $data]);
            return $default;

        } catch (\Throwable $e) {
            $this->logger->error('[GeoIP] Failed to geolocate IP', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);
            return $default;
        }
    }

    private function isPrivateIP(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost', '0.0.0.0'], true)) {
            return true;
        }

        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
