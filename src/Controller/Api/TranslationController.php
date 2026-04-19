<?php

namespace App\Controller\Api;

use App\Service\GoogleCloudTranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Translation API endpoint backed by Google Cloud with fallback provider.
 */
class TranslationController extends AbstractController
{
    #[Route('/translate', name: 'app_translate', methods: ['POST'])]
    public function translate(Request $request, GoogleCloudTranslationService $googleCloudTranslationService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $target = $data['target'] ?? 'fr';
            $source = $data['source'] ?? null;
            $text = $data['text'] ?? '';
            $texts = $data['texts'] ?? null;

            if (is_array($texts)) {
                $translations = [];
                foreach ($texts as $item) {
                    $sourceText = trim((string) $item);
                    if ($sourceText === '') {
                        continue;
                    }

                    $translated = $googleCloudTranslationService->translateText($sourceText, (string) $target, is_string($source) ? $source : null);
                    $translations[$sourceText] = is_string($translated) && trim($translated) !== '' ? $translated : $sourceText;
                }

                return new JsonResponse([
                    'success' => true,
                    'translations' => $translations,
                    'target' => $target,
                    'method' => 'Google Cloud Translation API',
                ]);
            }

            if (empty($text)) {
                return new JsonResponse(['success' => false, 'error' => 'Le texte est vide.'], 400);
            }

            $translatedText = $googleCloudTranslationService->translateText((string) $text, (string) $target, is_string($source) ? $source : null);
            if (!is_string($translatedText) || trim($translatedText) === '') {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Traduction indisponible pour le moment.',
                ], 503);
            }

            return new JsonResponse([
                'success' => true,
                'translated' => $translatedText,
                'target' => $target,
                'method' => 'Google Cloud Translation API',
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur Bundle : ' . $e->getMessage()
            ], 500);
        }
    }
}
