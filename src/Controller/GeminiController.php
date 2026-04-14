<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for Gemini AI integration
 */
class GeminiController extends AbstractController
{
    #[Route('/gemini/chat', name: 'app_gemini_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userMessage = $data['message'] ?? '';

        if (empty($userMessage)) {
            return new JsonResponse(['error' => 'Message cannot be empty'], 400);
        }

        $apiKey = $this->getParameter('gemini_api_key');
        if (!$apiKey || $apiKey === 'YOUR_GEMINI_API_KEY') {
            return new JsonResponse(['error' => 'Gemini API key not configured. Please add it to your .env file.'], 500);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $apiKey;

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userMessage]
                    ]
                ]
            ],
            'system_instruction' => [
                'parts' => [
                    ['text' => "You are a professional travel assistant for 'GoVibe'. 
                    HELPFUL COMMANDS:
                    If the user wants to create a post or share an experience, you can propose a publication.
                    To propose a post, provide your normal conversational response, then append a block in exactly this format:
                    [POST_SUGGESTION]
                    {
                      \"type\": \"STATUS\" or \"MEDIA\",
                      \"contenu\": \"The suggested post text (concise and engaging)\",
                      \"localisation\": \"City, Country\",
                      \"imageUrl\": \"A relevant Unsplash image URL if type is MEDIA, otherwise null\"
                    }
                    [/POST_SUGGESTION]
                    
                    IMPORTANT: 
                    - For images, use high-quality Unsplash URLs (e.g., https://images.unsplash.com/photo-xxx?auto=format&fit=crop&q=80&w=800).
                    - Be enthusiastic and travel-oriented."]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 1024,
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        // Fix for Windows SSL issue: Disable verification (Local development only)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return new JsonResponse(['error' => 'CURL Error: ' . $error], 500);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMessage = $errorData['error']['message'] ?? 'Gemini API Error';
            return new JsonResponse(['error' => $errorMessage], $httpCode > 0 ? $httpCode : 500);
        }

        $result = json_decode($response, true);
        $aiText = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I couldn\'t process that.';

        return new JsonResponse(['response' => $aiText]);
    }

    #[Route('/gemini/suggest', name: 'app_gemini_suggest', methods: ['POST'])]
    public function suggest(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $context = $data['context'] ?? '';
        $type = $data['type'] ?? 'comment'; // 'comment' or 'reply'

        if (empty($context)) {
            return new JsonResponse(['error' => 'Context text cannot be empty'], 400);
        }

        $apiKey = $this->getParameter('gemini_api_key');
        if (!$apiKey || $apiKey === 'YOUR_GEMINI_API_KEY') {
            return new JsonResponse(['error' => 'Gemini API key not configured.'], 500);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $apiKey;

        $promptType = $type === 'reply' ? 'répondre au commentaire suivant' : 'commenter la publication suivante';
        
        $prompt = "Tu es un assistant IA pour 'GoVibe' (réseau social de voyage).
        Génère 3 suggestions courtes, engageantes et orientées voyage pour $promptType.
        Texte d'origine : \"$context\"
        Format de réponse OBLIGATOIRE : Un JSON contenant un tableau de 3 chaînes de caractères (ex: [\"Super !\", \"Génial !\", \"Magnifique !\"]). Ne renvoie RIEN D'AUTRE que le JSON valide.";

        $payload = [
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $prompt]]]
            ],
            'generationConfig' => ['temperature' => 0.8]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) {
            return new JsonResponse(['error' => 'Erreur API Gemini'], 500);
        }

        $result = json_decode($response, true);
        $aiText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '[]';
        
        // Clean markdown backticks if AI added them
        $aiText = preg_replace('/```json\s*|\s*```/', '', $aiText);
        $suggestions = json_decode($aiText, true) ?? ["Super photo !", "Magnifique endroit !", "Ça donne envie de voyager !"];

        return new JsonResponse(['suggestions' => $suggestions]);
    }
}
