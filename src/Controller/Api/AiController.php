<?php
/**
 * AI Controller
 * 
 * REST API endpoints for AI model predictions.
 * Exposes predictions to frontend/AJAX calls.
 */

namespace App\Controller\Api;

use App\Service\AiClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/api/ai')]
class AiController extends AbstractController
{
    public function __construct(
        private AiClient $aiClient,
        private LoggerInterface $logger
    ) {}

    /**
     * Health check endpoint.
     * Verifies AI service is running and models are loaded.
     */
    #[Route('/health', name: 'api_ai_health', methods: ['GET'])]
    public function health(Request $request): JsonResponse
    {
        $this->releaseSessionLock($request);
        try {
            $health = $this->aiClient->health();

            return $this->json([
                'success' => $health['healthy'],
                'data' => $health,
            ], $health['healthy'] ? 200 : 503);
        } catch (\Throwable $e) {
            $this->logger->error('AI health check failed: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'AI service health check failed',
            ], 503);
        }
    }

    /**
     * List available models.
     *
     * @return JsonResponse
     */
    #[Route('/models', name: 'api_ai_models', methods: ['GET'])]
    public function listModels(): JsonResponse
    {
        try {
            $models = $this->aiClient->getAvailableModels();

            return $this->json([
                'success' => true,
                'data' => [
                    'models' => $models,
                    'count' => count($models),
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Error listing models: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Failed to list models',
            ], 500);
        }
    }

    /**
     * Make a prediction.
     * 
     * POST /api/ai/predict
     * 
     * Request body:
     * {
     *     "model": "xgb_weather_model",
     *     "features": [72.5, 65.3, 1013.2, 45],
     *     "return_proba": false
     * }
     */
    #[Route('/predict', methods: ['POST'])]
    public function predict(Request $request): JsonResponse
    {
        $this->releaseSessionLock($request);
        try {
            $data = json_decode($request->getContent(), true);

            // Validate required fields
            if (!is_array($data) || !isset($data['model']) || !isset($data['features'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Missing required fields: model, features',
                ], 400);
            }

            $modelName = (string) $data['model'];
            $features = (array) $data['features'];
            $returnProba = (bool) ($data['return_proba'] ?? false);

            // Validate features are numeric
            foreach ($features as $feature) {
                if (!is_numeric($feature)) {
                    return $this->json([
                        'success' => false,
                        'error' => 'Invalid features: all values must be numeric',
                    ], 400);
                }
            }

            // Make prediction
            $this->logger->info(sprintf(
                'Prediction request: model=%s, features_count=%d',
                $modelName,
                count($features)
            ));

            $result = $this->aiClient->predict($modelName, $features, $returnProba);

            if (!($result['success'] ?? false)) {
                return $this->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Prediction failed',
                ], 400);
            }

            return $this->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Prediction error: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Prediction request failed',
            ], 500);
        }
    }

    /**
     * Make batch predictions.
     * 
     * POST /api/ai/predict/batch
     * 
     * Request body:
     * {
     *     "model": "xgb_weather_model",
     *     "features_list": [
     *         [72.5, 65.3, 1013.2, 45],
     *         [70.2, 62.1, 1010.5, 50]
     *     ]
     * }
     */
    #[Route('/predict/batch', methods: ['POST'])]
    public function batchPredict(Request $request): JsonResponse
    {
        $this->releaseSessionLock($request);
        try {
            $data = json_decode($request->getContent(), true);

            // Validate required fields
            if (!is_array($data) || !isset($data['model']) || !isset($data['features_list'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Missing required fields: model, features_list',
                ], 400);
            }

            $modelName = (string) $data['model'];
            $featuresList = (array) $data['features_list'];

            if (empty($featuresList)) {
                return $this->json([
                    'success' => false,
                    'error' => 'features_list cannot be empty',
                ], 400);
            }

            $this->logger->info(sprintf(
                'Batch prediction request: model=%s, samples=%d',
                $modelName,
                count($featuresList)
            ));

            $result = $this->aiClient->batchPredict($modelName, $featuresList);

            return $this->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Batch prediction error: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Batch prediction failed',
            ], 500);
        }
    }

    /**
     * Predict weather impact on flights (integrated with Vol controller).
     * 
     * POST /api/ai/predict/weather
     * Used by: flights/vol page
     * 
     * Request body:
     * {
     *     "temperature": 72.5,
     *     "humidity": 65.3,
     *     "pressure": 1013.2,
     *     "wind_speed": 45
     * }
     */
    #[Route('/predict/weather', name: 'api_ai_predict_weather', methods: ['POST'])]
    public function predictWeather(Request $request): JsonResponse
    {
        $this->releaseSessionLock($request);
        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid JSON payload',
                ], 400);
            }

            if (isset($data['features']) && is_array($data['features'])) {
                $features = array_map('floatval', $data['features']);
                $result = $this->aiClient->predict('xgb_weather_model', $features);

                if (!($result['success'] ?? false)) {
                    return $this->json([
                        'success' => false,
                        'error' => 'Weather prediction failed',
                    ], 400);
                }

                return $this->json($result);
            }

            // Extract weather parameters
            $temperature = $data['temperature'] ?? null;
            $humidity = $data['humidity'] ?? null;
            $pressure = $data['pressure'] ?? null;
            $windSpeed = $data['wind_speed'] ?? null;

            if ($temperature === null || $humidity === null || $pressure === null || $windSpeed === null) {
                return $this->json([
                    'success' => false,
                    'error' => 'Missing weather parameters: temperature, humidity, pressure, wind_speed or features',
                ], 400);
            }

            $features = [
                (float) $temperature,
                (float) $humidity,
                (float) $pressure,
                (float) $windSpeed,
            ];

            while (count($features) < 23) {
                $features[] = 0.0;
            }

            $this->logger->info(sprintf(
                'Weather prediction: temp=%f, humidity=%f, pressure=%f, wind=%f',
                $temperature,
                $humidity,
                $pressure,
                $windSpeed
            ));

            $result = $this->aiClient->predict('xgb_weather_model', $features);

            if (!($result['success'] ?? false)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Weather prediction failed',
                ], 400);
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'weather_impact' => $result['prediction'][0] ?? null,
                    'model' => $result['model'],
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Weather prediction error: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Weather prediction failed',
            ], 500);
        }
    }

    /**
     * Predict booking probability for a flight.
     *
     * POST /api/ai/predict/booking
     */
    #[Route('/predict/booking', name: 'api_ai_predict_booking', methods: ['POST'])]
    public function predictBooking(Request $request): JsonResponse
    {
        $this->releaseSessionLock($request);
        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid JSON payload',
                ], 400);
            }

            $daysBefore = (int) ($data['days_before_departure'] ?? 30);
            $price = (float) ($data['price'] ?? 0);
            $seatsLeft = (int) ($data['seats_left'] ?? 0);
            $isWeekend = (bool) ($data['is_weekend'] ?? false);
            $isHoliday = (bool) ($data['is_holiday'] ?? false);
            $seatType = (int) ($data['seat_type'] ?? 0);

            $features = [
                (float) $daysBefore,
                $price,
                (float) $seatsLeft,
                $isWeekend ? 1.0 : 0.0,
                $isHoliday ? 1.0 : 0.0,
                (float) $seatType,
            ];

            $result = $this->aiClient->predict('xgb_model', $features, true);

            if (!($result['success'] ?? false)) {
                return $this->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Booking prediction failed',
                ], 400);
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'booking_probability' => $result['probability'] ?? $result['confidence'] ?? null,
                    'prediction' => $result['prediction'][0] ?? null,
                    'model' => $result['model'] ?? 'xgb_model',
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Booking prediction error: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Booking prediction failed',
            ], 500);
        }
    }

    /**
     * Predict booking risk (integrated with checkout page).
     * 
     * POST /api/ai/predict/risk
     * Used by: checkout page
     * 
     * Request body:
     * {
     *     "user_history": [1, 0, 1],
     *     "booking_amount": 150.50,
     *     "departure_days": 5
     * }
     */
    #[Route('/predict/risk', name: 'api_ai_predict_risk', methods: ['POST'])]
    public function predictRisk(Request $request): JsonResponse
    {
        $this->releaseSessionLock($request);
        try {
            $data = json_decode($request->getContent(), true);

            // Extract risk parameters
            $userHistory = $data['user_history'] ?? null;
            $bookingAmount = $data['booking_amount'] ?? null;
            $departureDays = $data['departure_days'] ?? null;

            if ($userHistory === null || $bookingAmount === null || $departureDays === null) {
                return $this->json([
                    'success' => false,
                    'error' => 'Missing risk parameters: user_history, booking_amount, departure_days',
                ], 400);
            }

            // Flatten user_history if it's an array
            $historyFeatures = is_array($userHistory) ? array_values($userHistory) : [$userHistory];
            $historyFeatures = array_map(static fn ($value) => (float) $value, $historyFeatures);

            // xgb_model expects 6 features.
            // Keep the most recent history values, then append booking context and pad with zeros.
            $historyFeatures = array_slice($historyFeatures, 0, 4);
            $features = array_merge($historyFeatures, [(float) $bookingAmount, (float) $departureDays]);
            while (count($features) < 6) {
                $features[] = 0.0;
            }

            $this->logger->info('Risk prediction for booking');

            $result = $this->aiClient->predict('xgb_model', $features, true);

            if (!($result['success'] ?? false)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Risk prediction failed',
                ], 400);
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'risk_level' => $result['prediction'][0] ?? null,
                    'confidence' => $result['confidence'] ?? null,
                    'model' => $result['model'],
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Risk prediction error: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Risk prediction failed',
            ], 500);
        }
    }

    /**
     * Predict booking and weather analytics in a single batch.
     *
     * POST /api/ai/predict/analytics-batch
     */
    #[Route('/predict/analytics-batch', name: 'api_ai_predict_analytics_batch', methods: ['POST'])]
    public function predictAnalyticsBatch(Request $request): JsonResponse
    {
        $this->releaseSessionLock($request);
        try {
            $data = json_decode($request->getContent(), true);

            if (!is_array($data) || !isset($data['flights']) || !is_array($data['flights'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Missing flights array',
                ], 400);
            }

            $results = [];
            foreach ($data['flights'] as $flight) {
                if (!is_array($flight)) {
                    continue;
                }

                $bookingFeatures = [
                    (float) ($flight['days_before_departure'] ?? 30),
                    (float) ($flight['price'] ?? 0),
                    (float) ($flight['seats_left'] ?? 0),
                    !empty($flight['is_weekend']) ? 1.0 : 0.0,
                    !empty($flight['is_holiday']) ? 1.0 : 0.0,
                    (float) ($flight['seat_type'] ?? 0),
                ];

                $weatherFeatures = array_map('floatval', (array) ($flight['weather_features'] ?? []));

                $booking = $this->aiClient->predict('xgb_model', $bookingFeatures, true);
                $weather = $this->aiClient->predict('xgb_weather_model', $weatherFeatures);

                $results[] = [
                    'flight_id' => $flight['flight_id'] ?? null,
                    'booking' => $booking,
                    'weather' => $weather,
                ];
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'flights' => $results,
                    'count' => count($results),
                ],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Analytics batch prediction error: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Analytics batch prediction failed',
            ], 500);
        }
    }

    private function releaseSessionLock(Request $request): void
    {
        if (!$request->hasSession()) {
            return;
        }

        try {
            $session = $request->getSession();
            if ($session->isStarted()) {
                $session->save();
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Skipping session unlock for AI endpoint: ' . $e->getMessage());
        }
    }
}

