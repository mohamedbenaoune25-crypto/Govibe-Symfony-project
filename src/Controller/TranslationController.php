<?php

namespace App\Controller;

use Stichoza\GoogleTranslate\GoogleTranslate;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller using the Stichoza Google Translate Bundle
 */
class TranslationController extends AbstractController
{
    #[Route('/translate', name: 'app_translate', methods: ['POST'])]
    public function translate(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $text = $data['text'] ?? '';
            $target = $data['target'] ?? 'fr';

            if (empty($text)) {
                return new JsonResponse(['success' => false, 'error' => 'Le texte est vide.'], 400);
            }

            // 1. Initialisation du "Bundle" (Library)
            $tr = new GoogleTranslate();
            
            // 2. Configuration des options pour Guzzle (Fix SSL pour Windows/Localhost)
            $tr->setOptions([
                'verify' => false,
                'timeout' => 5.0,
            ]);

            // 3. Configuration des langues
            $tr->setTarget($target);
            $tr->setSource('auto');

            // 4. Exécution de la traduction
            $translatedText = $tr->translate($text);
            $source = $tr->getLastDetectedSource();

            // 5. Gestion intelligente (si source = cible)
            if ($source === $target && $target === 'fr') {
                $tr->setTarget('en');
                $translatedText = $tr->translate($text);
                $target = 'en';
            }

            return new JsonResponse([
                'success' => true,
                'translated' => $translatedText,
                'source' => $source,
                'target' => $target,
                'method' => 'Stichoza Library (Bundle)'
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Erreur Bundle : ' . $e->getMessage()
            ], 500);
        }
    }
}
