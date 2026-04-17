<?php

namespace App\Service\AI;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class TtsService
{
    private HttpClientInterface $client;
    private string $elevenLabsApiKey;
    private string $elevenLabsVoiceId;

    public function __construct(
        HttpClientInterface $client,
        string $elevenLabsApiKey,
        string $elevenLabsVoiceId
    ) {
        $this->client = $client;
        $this->elevenLabsApiKey = $elevenLabsApiKey;
        $this->elevenLabsVoiceId = $elevenLabsVoiceId;
    }

    public function speak(string $text, string $voiceId = null): BinaryFileResponse|Response
    {
        $targetVoice = $voiceId ?? $this->elevenLabsVoiceId;
        
        try {
            $response = $this->client->request('POST', "https://api.elevenlabs.io/v1/text-to-speech/{$targetVoice}", [
                'headers' => [
                    'xi-api-key' => $this->elevenLabsApiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'audio/mpeg'
                ],
                'json' => [
                    'text' => $text,
                    'model_id' => 'eleven_multilingual_v2',
                    'voice_settings' => [
                        'stability' => 0.5,
                        'similarity_boost' => 0.75
                    ]
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $tempFile = tempnam(sys_get_temp_dir(), 'tts_');
                file_put_contents($tempFile, $response->getContent());
                
                $binaryResponse = new BinaryFileResponse($tempFile);
                $binaryResponse->headers->set('Content-Type', 'audio/mpeg');
                $binaryResponse->deleteFileAfterSend(true);
                return $binaryResponse;
            }
        } catch (\Exception $e) {
            // Fallthrough to fallback
        }

        // Google Cloud TTS Fallback logic would go here
        // If it also fails, or for now:
        return new Response('TTS Service Unavailable', 503);
    }
}
