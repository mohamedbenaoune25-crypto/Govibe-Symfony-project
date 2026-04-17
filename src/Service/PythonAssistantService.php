<?php

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PythonAssistantService
{
    private const TIMEOUT_SECONDS = 4;
    private const MAX_ATTEMPTS = 2;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private string $pythonAssistantApiBaseUrl = 'http://localhost:5000'
    ) {
        $this->pythonAssistantApiBaseUrl = rtrim($this->pythonAssistantApiBaseUrl, '/');
    }

    public function health(): array
    {
        return $this->request('GET', '/api/health');
    }

    public function classify(string $text): array
    {
        return $this->request('POST', '/api/classify', [
            'text' => $text,
        ]);
    }

    public function command(string $userInput, bool $isDryRun): array
    {
        return $this->request('POST', '/api/command', [
            'user_input' => $userInput,
            'is_dry_run' => $isDryRun,
        ]);
    }

    public function recalibrateNoise(): array
    {
        return $this->request('POST', '/api/noise/recalibrate');
    }

    public function processUserInput(string $userInput, bool $confirmed = false): array
    {
        $classification = $this->classify($userInput);

        if (!$classification['success']) {
            return $classification;
        }

        $dryRun = $this->command($userInput, true);

        if (!$dryRun['success']) {
            return $dryRun;
        }

        $classificationData = is_array($classification['data'] ?? null) ? $classification['data'] : [];
        $dryRunData = is_array($dryRun['data'] ?? null) ? $dryRun['data'] : [];
        $plan = is_array($dryRunData['plan'] ?? null) ? $dryRunData['plan'] : [];
        $requiresConfirmation = (bool) ($plan['requires_confirmation'] ?? false);

        if ($requiresConfirmation && !$confirmed) {
            return $this->successEnvelope([
                'matched' => $dryRunData['matched'] ?? null,
                'intent' => $dryRunData['intent'] ?? ($classificationData['intent'] ?? null),
                'plan' => $plan,
                'requires_confirmation' => true,
                'confirmation_prompt' => $plan['reason'] ?? 'Confirmation required before executing this command.',
                'classification' => $classificationData,
                'dry_run' => $dryRunData,
                'next_action' => 'confirm',
            ]);
        }

        $execution = $this->command($userInput, false);

        if (!$execution['success']) {
            return $execution;
        }

        $executionData = is_array($execution['data'] ?? null) ? $execution['data'] : [];

        return $this->successEnvelope([
            'matched' => $executionData['matched'] ?? ($dryRunData['matched'] ?? null),
            'intent' => $executionData['intent'] ?? ($classificationData['intent'] ?? null),
            'plan' => $executionData['plan'] ?? $plan,
            'requires_confirmation' => $requiresConfirmation,
            'confirmed' => $confirmed || !$requiresConfirmation,
            'classification' => $classificationData,
            'dry_run' => $dryRunData,
            'execution' => $executionData,
        ]);
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $url = $this->pythonAssistantApiBaseUrl . $path;
        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $options = [
                    'timeout' => self::TIMEOUT_SECONDS,
                ];

                if ($payload !== null) {
                    $options['json'] = $payload;
                }

                $response = $this->httpClient->request($method, $url, $options);
                $statusCode = $response->getStatusCode();

                if ($statusCode >= 500) {
                    $lastError = 'HTTP ' . $statusCode;

                    if ($attempt < self::MAX_ATTEMPTS) {
                        continue;
                    }

                    return $this->unavailableEnvelope('Le service IA Python est momentanément indisponible.');
                }

                $content = $response->toArray(false);

                return $this->normalizeEnvelope($content);
            } catch (TransportExceptionInterface $exception) {
                $lastError = $exception->getMessage();

                if ($attempt < self::MAX_ATTEMPTS) {
                    continue;
                }

                return $this->unavailableEnvelope('Le service IA Python est indisponible pour le moment.');
            } catch (\Throwable $exception) {
                $lastError = $exception->getMessage();

                if ($attempt < self::MAX_ATTEMPTS && $this->isTransientError($exception)) {
                    continue;
                }

                return $this->unavailableEnvelope('Le service IA Python est indisponible pour le moment.');
            }
        }

        $this->logger->warning('[PythonAssistant] Request failed after retries', [
            'url' => $url,
            'error' => $lastError,
        ]);

        return $this->unavailableEnvelope('Le service IA Python est indisponible pour le moment.');
    }

    private function normalizeEnvelope(array $payload): array
    {
        if (array_key_exists('success', $payload) || array_key_exists('data', $payload) || array_key_exists('error', $payload)) {
            return [
                'success' => (bool) ($payload['success'] ?? false),
                'data' => $payload['data'] ?? null,
                'error' => $payload['error'] ?? null,
                'timestamp' => $payload['timestamp'] ?? $this->utcTimestamp(),
            ];
        }

        return [
            'success' => true,
            'data' => $payload,
            'error' => null,
            'timestamp' => $this->utcTimestamp(),
        ];
    }

    private function successEnvelope(array $data): array
    {
        return [
            'success' => true,
            'data' => $data,
            'error' => null,
            'timestamp' => $this->utcTimestamp(),
        ];
    }

    private function unavailableEnvelope(string $message): array
    {
        $this->logger->warning('[PythonAssistant] Service unavailable', [
            'base_url' => $this->pythonAssistantApiBaseUrl,
            'message' => $message,
        ]);

        return [
            'success' => false,
            'data' => null,
            'error' => $message,
            'timestamp' => $this->utcTimestamp(),
        ];
    }

    private function utcTimestamp(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
    }

    private function isTransientError(\Throwable $exception): bool
    {
        return $exception instanceof TransportExceptionInterface
            || str_contains(strtolower($exception->getMessage()), 'timeout')
            || str_contains(strtolower($exception->getMessage()), 'temporarily');
    }
}