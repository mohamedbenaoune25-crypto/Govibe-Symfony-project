<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Symfony HTTP client wrapper for the Python AI inference service.
 * Calls the FastAPI endpoints to get booking and weather predictions.
 */
class AiClientService
{
    private const TIMEOUT = 2;
    private const MAX_RETRIES = 2;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface     $logger,
        private readonly string              $aiApiBaseUrl = 'http://127.0.0.1:8000',
    ) {
    }

    /**
     * Predict booking probability for a flight checkout.
     *
     * @param int   $daysBefore  Days before departure
     * @param float $price       Ticket price
     * @param int   $seatsLeft   Available seats
     * @param bool  $isWeekend   Whether departure is on a weekend
     * @param bool  $isHoliday   Whether departure is on a holiday
     * @param int   $seatType    0=economy, 1=business, 2=first
     */
    public function predictBooking(
        int   $daysBefore,
        float $price,
        int   $seatsLeft,
        bool  $isWeekend = false,
        bool  $isHoliday = false,
        int   $seatType = 0,
    ): array {
        return $this->request('POST', '/api/predict/booking', [
            'days_before_departure' => $daysBefore,
            'price'                 => $price,
            'seats_left'            => $seatsLeft,
            'is_weekend'            => $isWeekend ? 1 : 0,
            'is_holiday'            => $isHoliday ? 1 : 0,
            'seat_type'             => $seatType,
        ]);
    }

    /**
     * Predict weather impact on a flight.
     *
     * @param array $features  Weather feature vector
     */
    public function predictWeather(array $features): array
    {
        return $this->request('POST', '/api/predict/weather', [
            'features' => $features,
        ]);
    }

    /**
     * Health check — verify AI service is running.
     */
    public function health(): array
    {
        return $this->request('GET', '/api/health');
    }

    /**
     * List available models.
     */
    public function listModels(): array
    {
        return $this->request('GET', '/api/models');
    }

    /**
     * Generic prediction.
     */
    public function predict(string $modelName, array $features, bool $returnProba = false): array
    {
        return $this->request('POST', '/api/predict', [
            'model_name'   => $modelName,
            'features'     => $features,
            'return_proba' => $returnProba,
        ]);
    }

    /**
     * Batch predict analytics (booking & weather) for multiple flights.
     */
    public function predictFlightAnalyticsBatch(array $flights): array
    {
        return $this->request('POST', '/api/predict/analytics-batch', [
            'flights' => $flights,
        ]);
    }

    // ── Internal HTTP request with retry ───────────────────────────
    private function request(string $method, string $path, ?array $payload = null): array
    {
        $url = rtrim($this->aiApiBaseUrl, '/') . $path;
        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $options = ['timeout' => self::TIMEOUT];
                if ($payload !== null) {
                    $options['json'] = $payload;
                }

                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();

                if ($statusCode >= 500) {
                    $lastError = 'HTTP ' . $statusCode;
                    if ($attempt < self::MAX_RETRIES) {
                        continue;
                    }
                    return $this->errorResponse('Le service IA est temporairement indisponible.');
                }

                $content = $response->toArray(false);
                return $this->normalizeResponse($content);

            } catch (TransportExceptionInterface $e) {
                $lastError = $e->getMessage();
                if ($attempt < self::MAX_RETRIES) {
                    continue;
                }
                return $this->errorResponse('Le service IA est indisponible.');
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                return $this->errorResponse('Erreur lors de la communication avec le service IA.');
            }
        }

        $this->logger->warning('[AiClient] All retries failed', ['url' => $url, 'error' => $lastError]);
        return $this->errorResponse('Le service IA est indisponible.');
    }

    private function normalizeResponse(array $data): array
    {
        if (isset($data['success'])) {
            return $data;
        }
        return ['success' => true, 'data' => $data];
    }

    private function errorResponse(string $message): array
    {
        $this->logger->warning('[AiClient] Service unavailable', ['message' => $message]);
        return [
            'success' => false,
            'error'   => $message,
        ];
    }
}
