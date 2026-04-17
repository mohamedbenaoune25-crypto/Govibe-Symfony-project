<?php

namespace App\Controller\Api;

use App\Service\AI\TtsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class VoiceController extends AbstractController
{
    private TtsService $ttsService;

    public function __construct(TtsService $ttsService)
    {
        $this->ttsService = $ttsService;
    }

    #[Route('/api/voice/speak', name: 'api_voice_speak', methods: ['POST'])]
    public function speak(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $text = $data['text'] ?? '';
        $voice = $data['voice'] ?? null;

        if (empty($text)) {
            return new Response('No text provided.', 400);
        }

        // We can pass $voice if we want to map "qween" to specific elevenlabs IDs
        // For now, TtsService will use the default ELEVENLABS_VOICE_ID if not provided
        $voiceId = null;
        if ($voice === 'qween') {
            // Optional mapping, using default directly 
        }

        return $this->ttsService->speak($text, $voiceId);
    }
}
