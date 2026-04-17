<?php
/**
 * AiClient Service
 * 
 * Handles HTTP communication with the Python AI service.
 * Provides a clean interface for making predictions from Symfony controllers.
 * 
 * Usage:
 *   $prediction = $aiClient->predict('xgb_weather_model', [72.5, 65.3, 1013.2, 45]);
 */

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class AiClient
{
    private HttpClientInterface $httpClient;
    private string $apiBaseUrl;
    private LoggerInterface $logger;
    private int $timeout = 2; // Reduced from 10 to avoid locking the app
    private int $maxRetries = 1; // Reduced from 2 to avoid blocking too long

    public function __construct(
        HttpClientInterface $httpClient,
        ParameterBagInterface $parameterBag,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->apiBaseUrl = $parameterBag->get('ai_api_base_url');
        $this->logger = $logger;
    }

    /**
     * Make a single prediction.
     *
     * @param string $modelName Name of the model to use
     * @param array $features Input features array
     * @param bool $returnProba Return probabilities for classification
     *
     * @return array Prediction result with keys: success, model, prediction, type, error
     */
    public function predict(string $modelName, array $features, bool $returnProba = false): array
    {
        $payload = [
            'model_name' => $modelName,
            'features' => array_values($features),
            'return_proba' => $returnProba,
        ];

        return $this->sendRequest('POST', '/api/predict', $payload);
    }

    /**
     * Make batch predictions.
     *
     * @param string $modelName Name of the model
     * @param array $featuresList List of feature arrays
     *
     * @return array Batch prediction results
     */
    public function batchPredict(string $modelName, array $featuresList): array
    {
        $payload = [
            'model_name' => $modelName,
            'features_list' => $featuresList,
        ];

        return $this->sendRequest('POST', '/api/predict/batch', $payload);
    }

    /**
     * Get available models and their types.
     *
     * @return array List of available models
     */
    public function getAvailableModels(): array
    {
        $response = $this->sendRequest('GET', '/api/models');
        
        if ($response['success'] ?? false) {
            return $response['models'] ?? [];
        }

        return [];
    }

    /**
     * Check AI service health and connectivity.
     *
     * @return array Health status
     */
    public function health(): array
    {
        $response = $this->sendRequest('GET', '/api/health');
        
        return [
            'healthy' => $response['status'] === 'healthy',
            'models_loaded' => $response['models_loaded'] ?? 0,
            'available_models' => $response['available_models'] ?? [],
            'message' => $response['message'] ?? 'Unknown',
        ];
    }

    /**
     * Send HTTP request to AI service with retry logic.
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint path
     * @param array|null $payload Request payload (for POST)
     *
     * @return array Parsed response
     */
    private function sendRequest(string $method, string $endpoint, ?array $payload = null): array
    {
        $url = rtrim($this->apiBaseUrl, '/') . $endpoint;
        $attempt = 0;

        while ($attempt < $this->maxRetries) {
            try {
                $attempt++;

                $options = [
                    'timeout' => $this->timeout,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                ];

                if ($payload !== null && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                    $options['json'] = $payload;
                }

                $this->logger->info(sprintf(
                    'AI Request [%s #%d]: %s %s',
                    $method,
                    $attempt,
                    $method,
                    $endpoint
                ));

                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();

                // Successful response
                if ($statusCode >= 200 && $statusCode < 300) {
                    $content = $response->getContent();
                    $data = json_decode($content, true);

                    $this->logger->info(sprintf(
                        'AI Response [%d]: Successfully received response from %s',
                        $statusCode,
                        $endpoint
                    ));

                    return $data ?? [
                        'success' => false,
                        'error' => 'Invalid JSON response',
                    ];
                }

                // Client error - don't retry
                if ($statusCode >= 400 && $statusCode < 500) {
                    $this->logger->error(sprintf(
                        'AI Error [%d]: Client error for %s: %s',
                        $statusCode,
                        $endpoint,
                        $response->getContent()
                    ));

                    return [
                        'success' => false,
                        'error' => "Client error: {$statusCode}",
                    ];
                }

                // Server error - may retry
                if ($statusCode >= 500) {
                    $this->logger->warning(sprintf(
                        'AI Error [%d]: Server error for %s (attempt %d/%d)',
                        $statusCode,
                        $endpoint,
                        $attempt,
                        $this->maxRetries
                    ));

                    if ($attempt < $this->maxRetries) {
                        usleep(500000); // Wait 500ms before retry
                        continue;
                    }

                    return [
                        'success' => false,
                        'error' => "Server error: {$statusCode}",
                    ];
                }
            } catch (TransportException $e) {
                $this->logger->warning(sprintf(
                    'AI Transport Error [attempt %d/%d]: %s',
                    $attempt,
                    $this->maxRetries,
                    $e->getMessage()
                ));

                if ($attempt < $this->maxRetries) {
                    usleep(500000); // Wait 500ms before retry
                    continue;
                }

                return [
                    'success' => false,
                    'error' => 'Service unavailable: ' . $e->getMessage(),
                ];
            } catch (Throwable $e) {
                $this->logger->error(sprintf(
                    'AI Unexpected Error: %s',
                    $e->getMessage()
                ));

                return [
                    'success' => false,
                    'error' => 'Unexpected error: ' . $e->getMessage(),
                ];
            }
        }

        return [
            'success' => false,
            'error' => 'Max retries exceeded',
        ];
    }

    /**
     * Set custom timeout for requests (in seconds).
     *
     * @param int $timeout Timeout in seconds
     *
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        $this->timeout = max(1, $timeout);

        return $this;
    }

    /**
     * Set max retry attempts.
     *
     * @param int $retries Number of retries
     *
     * @return self
     */
    public function setMaxRetries(int $retries): self
    {
        $this->maxRetries = max(1, $retries);

        return $this;
    }
}
