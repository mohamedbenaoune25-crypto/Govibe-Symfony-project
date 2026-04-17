<?php

namespace App\Controller\Api;

use App\Service\PythonAssistantService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/python-assistant')]
class PythonAssistantController extends AbstractController
{
    #[Route('/health', name: 'app_api_python_assistant_health', methods: ['GET'])]
    public function health(Request $request, PythonAssistantService $pythonAssistantService): JsonResponse
    {
        $this->releaseSessionLock($request);
        return new JsonResponse($pythonAssistantService->health());
    }

    #[Route('/flow', name: 'app_api_python_assistant_flow', methods: ['POST'])]
    public function flow(Request $request, PythonAssistantService $pythonAssistantService): JsonResponse
    {
        $this->releaseSessionLock($request);
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return new JsonResponse([
                'success' => false,
                'data' => null,
                'error' => 'Invalid JSON payload.',
                'timestamp' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            ], 400);
        }

        $userInput = trim((string) ($payload['user_input'] ?? ''));
        $confirmed = (bool) ($payload['confirmed'] ?? false);

        if ($userInput === '') {
            return new JsonResponse([
                'success' => false,
                'data' => null,
                'error' => 'user_input is required.',
                'timestamp' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(DATE_ATOM),
            ], 400);
        }

        $result = $pythonAssistantService->processUserInput($userInput, $confirmed);

        return new JsonResponse($result, $result['success'] ? 200 : 503);
    }

    #[Route('/noise/recalibrate', name: 'app_api_python_assistant_noise_recalibrate', methods: ['POST'])]
    public function noiseRecalibrate(Request $request, PythonAssistantService $pythonAssistantService): JsonResponse
    {
        $this->releaseSessionLock($request);
        $result = $pythonAssistantService->recalibrateNoise();

        return new JsonResponse($result, $result['success'] ? 200 : 503);
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
            // Silently ignore - session may not be active
        }
    }
}