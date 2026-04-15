<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class GrokController extends AbstractController
{
    #[Route('/groq/chat', name: 'app_groq_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userMessage = $data['message'] ?? '';

        if (empty($userMessage)) {
            return new JsonResponse(['error' => 'Message cannot be empty'], 400);
        }

        $apiKey = $_ENV['GROQ_API_KEY'] ?? $this->getParameter('groq_api_key');
        
        if (!$apiKey) {
            return new JsonResponse(['error' => 'Groq API key not configured.'], 500);
        }

        $url = "https://api.groq.com/openai/v1/chat/completions";

        $payload = [
            'model' => 'llama3-70b-8192',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "You are a professional travel assistant for 'GoVibe'. 
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
                    - For images, use high-quality Unsplash URLs.
                    - Be enthusiastic and travel-oriented."
                ],
                [
                    'role' => 'user',
                    'content' => $userMessage
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return new JsonResponse(['error' => 'CURL Error'], 500);
        }

        $result = json_decode($response, true);
        $aiText = $result['choices'][0]['message']['content'] ?? 'Sorry, I couldn\'t process that.';

        return new JsonResponse(['response' => $aiText]);
    }

    #[Route('/groq/suggest', name: 'app_groq_suggest', methods: ['POST'])]
    public function suggest(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $context = $data['context'] ?? '';
        $type = $data['type'] ?? 'comment';

        if (empty($context)) {
            return new JsonResponse(['error' => 'Context text cannot be empty'], 400);
        }

        $apiKey = $_ENV['GROQ_API_KEY'] ?? $this->getParameter('groq_api_key');
        if (!$apiKey) {
            return new JsonResponse(['error' => 'Groq API key not configured.'], 500);
        }

        $url = "https://api.groq.com/openai/v1/chat/completions";
        $promptType = $type === 'reply' ? 'répondre au commentaire suivant' : 'commenter la publication suivante';
        
        $prompt = "Tu es un assistant IA pour 'GoVibe' (réseau social de voyage).
        Génère 3 suggestions courtes, engageantes et orientées voyage pour $promptType.
        Texte d'origine : \"$context\"
        Format de réponse OBLIGATOIRE : Un JSON contenant un tableau de 3 chaînes de caractères (ex: [\"Super !\", \"Génial !\", \"Magnifique !\"]). Ne renvoie RIEN D'AUTRE que le JSON valide.";

        $payload = [
            'model' => 'llama3-70b-8192',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.8
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$response) {
            return new JsonResponse(['error' => 'Erreur API Groq'], 500);
        }

        $result = json_decode($response, true);
        
        if (isset($result['error'])) {
            return new JsonResponse(['error' => 'Groq API: ' . ($result['error']['message'] ?? 'Erreur inconnue')], 500);
        }

        $aiText = $result['choices'][0]['message']['content'] ?? '[]';
        
        $aiText = preg_replace('/```json\s*|\s*```/', '', $aiText);
        $suggestions = json_decode($aiText, true);

        if (!$suggestions || !is_array($suggestions) || count($suggestions) === 0) {
            $suggestions = ["👍 Super photo !", "Magnifique !", "Génial !"];
        }

        return new JsonResponse(['suggestions' => $suggestions]);
    }
}
