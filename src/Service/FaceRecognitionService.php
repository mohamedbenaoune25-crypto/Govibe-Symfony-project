<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Face Recognition Service — HTTP client for the Python FastAPI microservice.
 *
 * Provides face encoding (enrollment) and verification (authentication)
 * by communicating with the Python API at localhost:5002.
 *
 * Used by:
 *   - RegistrationController (mandatory face enrollment)
 *   - SecurityController (Face ID login + MFA verification)
 *   - UserController (profile face management)
 */
class FaceRecognitionService
{
    private const API_TIMEOUT = 15; // seconds — face processing is slower

    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $apiUrl;

    public function __construct(
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
        string $faceRecognitionApiUrl = 'http://localhost:5002'
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->apiUrl = rtrim($faceRecognitionApiUrl, '/');
    }

    /**
     * Extract a face encoding vector from a base64 image.
     *
     * Used during registration (mandatory) and profile face enrollment.
     *
     * @param string $base64Image Base64-encoded image (with or without data URI prefix)
     *
     * @return array{
     *     success: bool,
     *     encoding: ?array,
     *     face_count: int,
     *     message: string
     * }
     */
    public function encodeFace(string $base64Image): array
    {
        $this->logger->info('[FaceRecognition] Encoding face...');

        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . '/encode-face', [
                'json' => ['image' => $base64Image],
                'timeout' => self::API_TIMEOUT,
            ]);

            $data = $response->toArray(false);

            if (!empty($data['success']) && !empty($data['encoding'])) {
                $this->logger->info('[FaceRecognition] Face encoded successfully', [
                    'face_count' => $data['face_count'] ?? 0,
                    'encoding_dimensions' => count($data['encoding']),
                ]);

                return [
                    'success' => true,
                    'encoding' => $data['encoding'],
                    'face_count' => $data['face_count'] ?? 0,
                    'message' => $data['message'] ?? 'Visage encodé avec succès.',
                ];
            }

            $this->logger->warning('[FaceRecognition] Encoding failed', [
                'message' => $data['message'] ?? 'Unknown error',
            ]);

            return [
                'success' => false,
                'encoding' => null,
                'face_count' => $data['face_count'] ?? 0,
                'message' => $data['message'] ?? 'Échec de l\'encodage du visage.',
            ];

        } catch (\Throwable $e) {
            $this->logger->error('[FaceRecognition] API error during encoding', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'encoding' => null,
                'face_count' => 0,
                'message' => 'Service de reconnaissance faciale indisponible. Veuillez réessayer.',
            ];
        }
    }

    /**
     * Verify a face image against a stored encoding.
     *
     * Used during Face ID login and MFA face verification.
     *
     * @param string $base64Image  Base64-encoded image to verify
     * @param array  $storedEncoding  Previously stored encoding from DB
     *
     * @return array{
     *     success: bool,
     *     match: bool,
     *     distance: float,
     *     confidence: int,
     *     message: string
     * }
     */
    public function verifyFace(string $base64Image, array $storedEncoding): array
    {
        $this->logger->info('[FaceRecognition] Verifying face...');

        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . '/verify-face', [
                'json' => [
                    'image' => $base64Image,
                    'stored_encoding' => $storedEncoding,
                ],
                'timeout' => self::API_TIMEOUT,
            ]);

            $data = $response->toArray(false);

            if (!empty($data['success'])) {
                $this->logger->info('[FaceRecognition] Verification result', [
                    'match' => $data['match'] ?? false,
                    'distance' => $data['distance'] ?? 1.0,
                    'confidence' => $data['confidence'] ?? 0,
                ]);

                return [
                    'success' => true,
                    'match' => $data['match'] ?? false,
                    'distance' => (float) ($data['distance'] ?? 1.0),
                    'confidence' => (int) ($data['confidence'] ?? 0),
                    'message' => $data['message'] ?? '',
                ];
            }

            return [
                'success' => false,
                'match' => false,
                'distance' => 1.0,
                'confidence' => 0,
                'message' => $data['message'] ?? 'Échec de la vérification.',
            ];

        } catch (\Throwable $e) {
            $this->logger->error('[FaceRecognition] API error during verification', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'match' => false,
                'distance' => 1.0,
                'confidence' => 0,
                'message' => 'Service de reconnaissance faciale indisponible.',
            ];
        }
    }

    /**
     * Check if the Face Recognition API is available.
     */
    public function isServiceAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->apiUrl . '/health', [
                'timeout' => 3,
            ]);

            $data = $response->toArray(false);
            return ($data['status'] ?? '') === 'ok';

        } catch (\Throwable $e) {
            $this->logger->warning('[FaceRecognition] Health check failed', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
