<?php

namespace App\Controller\Api;

use App\Service\PythonAssistantService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Voice Command Controller
 *
 * Parses voice transcripts into structured app actions.
 * Fast keyword matching for navigation, falls back to PythonAssistantService for complex queries.
 */
#[Route('/api/voice')]
class VoiceCommandController extends AbstractController
{
    public function __construct(
        private readonly PythonAssistantService $pythonAssistant,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * Process a voice command transcript and return a structured action.
     *
     * POST /api/voice/command
     * { "transcript": "find cheapest flight to paris", "page": "vol_index" }
     *
     * Response:
     * { "action": "navigate", "url": "/vols?sort=price_asc&destination=paris",
     *   "spoken_response": "...", "success": true }
     */
    #[Route('/command', name: 'api_voice_command', methods: ['POST'])]
    public function command(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return $this->errorResponse('Invalid JSON payload.');
        }

        $transcript = mb_strtolower(trim((string) ($payload['transcript'] ?? '')));
        $page = (string) ($payload['page'] ?? 'unknown');

        if ($transcript === '') {
            return $this->errorResponse('Transcript is empty.');
        }

        // ── 1. Try fast keyword-based action matching ─────────────────
        $action = $this->matchAction($transcript, $page);

        if ($action !== null) {
            return $this->json($action);
        }

        // ── 2. Fallback: delegate to PythonAssistantService ──────────
        try {
            $result = $this->pythonAssistant->processUserInput($transcript, false);

            if ($result['success'] ?? false) {
                $data = $result['data'] ?? [];
                return $this->json([
                    'action'          => 'ai_response',
                    'url'             => null,
                    'spoken_response' => $this->extractVoiceReply($data),
                    'data'            => $data,
                    'success'         => true,
                ]);
            }
        } catch (\Throwable) {
            // Voice agent unavailable, provide graceful fallback
        }

        // ── 3. Final fallback ────────────────────────────────────────
        return $this->json([
            'action'          => 'none',
            'url'             => null,
            'spoken_response' => 'Je n\'ai pas compris cette commande. Essayez "vol pas cher" ou "mes réservations".',
            'success'         => false,
        ]);
    }

    /**
     * Health check for voice subsystem.
     */
    #[Route('/health', name: 'api_voice_health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $assistantHealth = $this->pythonAssistant->health();

        return $this->json([
            'success' => true,
            'voice_system' => 'operational',
            'python_assistant' => $assistantHealth['success'] ?? false,
            'capabilities' => [
                'speech_recognition' => 'browser_native',
                'tts'                => 'browser_native',
                'command_parsing'    => 'keyword_matching',
                'ai_fallback'        => 'python_assistant',
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // PRIVATE — Command Matching Engine
    // ═══════════════════════════════════════════════════════════════

    private function matchAction(string $transcript, string $page): ?array
    {
        // ── Navigation Commands ──────────────────────────────────────
        $navCommands = [
            [
                'patterns' => ['go home', 'retour accueil', 'page principale', 'accueil', 'return home'],
                'route'    => 'app_user_home',
                'params'   => [],
                'spoken'   => 'Retour à l\'accueil.',
            ],
            [
                'patterns' => ['mes réservations', 'my bookings', 'mes checkouts', 'show my bookings', 'mes billets'],
                'route'    => 'app_checkout_index',
                'params'   => [],
                'spoken'   => 'Voici vos réservations.',
            ],
            [
                'patterns' => ['voir les vols', 'show flights', 'page des vols', 'les vols', 'afficher les vols'],
                'route'    => 'app_vols_index',
                'params'   => [],
                'spoken'   => 'Voici la page des vols.',
            ],
            [
                'patterns' => ['voir les hôtels', 'show hotels', 'les hôtels', 'page hôtels'],
                'route'    => 'app_hotel_index',
                'params'   => [],
                'spoken'   => 'Voici les hôtels disponibles.',
            ],
            [
                'patterns' => ['voir les voitures', 'show cars', 'location voiture', 'les voitures'],
                'route'    => 'app_location_index',
                'params'   => [],
                'spoken'   => 'Voici les voitures disponibles.',
            ],
            [
                'patterns' => ['voir les activités', 'show activities', 'les activités', 'excursions'],
                'route'    => 'app_activite_index',
                'params'   => [],
                'spoken'   => 'Voici les activités disponibles.',
            ],
            [
                'patterns' => ['mon profil', 'my profile', 'paramètres', 'mon compte'],
                'route'    => 'app_user_profile',
                'params'   => [],
                'spoken'   => 'Voici votre profil.',
            ],
            [
                'patterns' => ['réclamation', 'plainte', 'signaler', 'problème'],
                'route'    => 'app_reclamation_index',
                'params'   => [],
                'spoken'   => 'Page des réclamations.',
            ],
            [
                'patterns' => ['publications', 'les posts', 'forum', 'blog'],
                'route'    => 'app_poste_index',
                'params'   => [],
                'spoken'   => 'Voici les publications.',
            ],
            [
                'patterns' => ['hôtel pas cher', 'cheapest hotel', 'hôtel le moins cher'],
                'route'    => 'app_hotel_index',
                'params'   => ['sort' => 'price'],
                'spoken'   => 'Voici les hôtels les moins chers.',
            ],
            [
                'patterns' => ['meilleur hôtel', 'best hotel', 'best rated hotel', 'top hôtel'],
                'route'    => 'app_hotel_index',
                'params'   => ['sort' => 'rating'],
                'spoken'   => 'Voici les hôtels les mieux notés.',
            ],
            [
                'patterns' => ['log in', 'login', 'se connecter', 'connexion', 'sign in', 'connecte-moi'],
                'route'    => 'app_login',
                'params'   => [],
                'spoken'   => 'Redirection vers la page de connexion.',
            ],
            [
                'patterns' => ['log out', 'logout', 'se déconnecter', 'déconnexion', 'sign out'],
                'route'    => 'app_logout',
                'params'   => [],
                'spoken'   => 'Déconnexion en cours.',
            ],
        ];

        // Admin commands
        $adminCommands = [
            [
                'patterns' => ['admin dashboard', 'tableau de bord', 'dashboard admin', 'admin panel'],
                'route'    => 'app_admin_dashboard',
                'params'   => [],
                'spoken'   => 'Tableau de bord administrateur.',
            ],
            [
                'patterns' => ['gérer les utilisateurs', 'manage users', 'open users', 'show users', 'liste utilisateurs'],
                'route'    => 'app_admin_users',
                'params'   => [],
                'spoken'   => 'Gestion des utilisateurs.',
            ],
            [
                'patterns' => ['les réclamations admin', 'admin reclamations', 'gérer réclamations'],
                'route'    => 'app_admin_reclamations',
                'params'   => [],
                'spoken'   => 'Gestion des réclamations.',
            ],
        ];

        $allCommands = array_merge($navCommands, $adminCommands);

        foreach ($allCommands as $cmd) {
            foreach ($cmd['patterns'] as $pattern) {
                if (str_contains($transcript, $pattern)) {
                    try {
                        $url = $this->urlGenerator->generate($cmd['route'], $cmd['params']);
                    } catch (\Throwable) {
                        continue;
                    }
                    return [
                        'action'          => 'navigate',
                        'url'             => $url,
                        'spoken_response' => $cmd['spoken'],
                        'success'         => true,
                    ];
                }
            }
        }

        // ── Destination-aware flight search ──────────────────────────
        $flightDest = $this->extractFlightDestination($transcript);
        if ($flightDest !== null) {
            try {
                $url = $this->urlGenerator->generate('app_vols_index', ['destination' => $flightDest]);
            } catch (\Throwable) {
                $url = '/vols?destination=' . urlencode($flightDest);
            }
            return [
                'action'          => 'navigate',
                'url'             => $url,
                'spoken_response' => 'Recherche de vols vers ' . ucfirst($flightDest) . '.',
                'success'         => true,
            ];
        }

        // ── Cheapest flight ─────────────────────────────────────────
        if ($this->containsAny($transcript, ['cheapest flight', 'vol pas cher', 'vol le moins cher', 'low cost', 'prix bas'])) {
            return [
                'action'          => 'navigate',
                'url'             => '/vols?sort=price_asc',
                'spoken_response' => 'Voici les vols les moins chers.',
                'success'         => true,
            ];
        }

        // ── Best flight / AI pick ──────────────────────────────────
        if ($this->containsAny($transcript, ['best flight', 'meilleur vol', 'ai pick', 'recommandé', 'top vol'])) {
            return [
                'action'          => 'scroll_to',
                'url'             => null,
                'target'          => '.gv-vl-card',
                'spoken_response' => 'Voici le vol recommandé par notre intelligence artificielle.',
                'success'         => true,
            ];
        }

        // ── Flights tomorrow ───────────────────────────────────────
        if ($this->containsAny($transcript, ['vols demain', 'flights tomorrow', 'demain', 'tomorrow flights'])) {
            return [
                'action'          => 'navigate',
                'url'             => '/vols?date=tomorrow',
                'spoken_response' => 'Recherche des vols de demain.',
                'success'         => true,
            ];
        }

        // ── Hotel search with city ──────────────────────────────────
        $hotelCity = $this->extractCity($transcript, ['hôtel', 'hotel', 'hébergement']);
        if ($hotelCity !== null) {
            try {
                $url = $this->urlGenerator->generate('app_hotel_index', ['ville' => $hotelCity]);
            } catch (\Throwable) {
                $url = '/hotel?ville=' . urlencode($hotelCity);
            }
            return [
                'action'          => 'navigate',
                'url'             => $url,
                'spoken_response' => 'Recherche d\'hôtels à ' . ucfirst($hotelCity) . '.',
                'success'         => true,
            ];
        }

        // ── Page-specific actions ───────────────────────────────────
        if ($page === 'checkout_index' || $page === 'checkout_show') {
            if ($this->containsAny($transcript, ['payer', 'pay now', 'proceed to payment', 'confirmer le paiement'])) {
                return [
                    'action'          => 'trigger_payment',
                    'url'             => null,
                    'spoken_response' => 'Lancement du paiement.',
                    'success'         => true,
                ];
            }
            if ($this->containsAny($transcript, ['annuler', 'cancel booking', 'supprimer la réservation'])) {
                return [
                    'action'          => 'cancel_booking',
                    'url'             => null,
                    'spoken_response' => 'Confirmez-vous l\'annulation de cette réservation ?',
                    'success'         => true,
                ];
            }
            if ($this->containsAny($transcript, ['repeat summary', 'répéter', 'résumé'])) {
                return [
                    'action'          => 'read_summary',
                    'url'             => null,
                    'spoken_response' => null, // JS will read the summary from DOM
                    'success'         => true,
                ];
            }
            if ($this->containsAny($transcript, ['combien', 'how much', 'total', 'le prix'])) {
                return [
                    'action'          => 'read_total',
                    'url'             => null,
                    'spoken_response' => null, // JS will read price from DOM
                    'success'         => true,
                ];
            }
        }

        // ── Admin-specific page actions ─────────────────────────────
        if ($page === 'admin_dashboard') {
            if ($this->containsAny($transcript, ['monthly revenue', 'revenus mensuels', 'revenu du mois', 'show revenue', 'montrer revenus'])) {
                return [
                    'action'          => 'scroll_to',
                    'url'             => null,
                    'target'          => '#corporateChart',
                    'spoken_response' => 'Voici les statistiques de revenus.',
                    'success'         => true,
                ];
            }
            if ($this->containsAny($transcript, ['popular destinations', 'destinations populaires'])) {
                return [
                    'action'          => 'scroll_to',
                    'url'             => null,
                    'target'          => '#popularDestinations',
                    'spoken_response' => 'Voici les destinations les plus populaires.',
                    'success'         => true,
                ];
            }
            if ($this->containsAny($transcript, ['user growth', 'croissance utilisateurs', 'show user growth', 'nouveaux utilisateurs'])) {
                return [
                    'action'          => 'scroll_to',
                    'url'             => null,
                    'target'          => '#userGrowthChart',
                    'spoken_response' => 'Voici la croissance des utilisateurs.',
                    'success'         => true,
                ];
            }
            if ($this->containsAny($transcript, ['payment stats', 'statistiques paiement', 'show payment', 'paiements'])) {
                return [
                    'action'          => 'scroll_to',
                    'url'             => null,
                    'target'          => '#paymentStats',
                    'spoken_response' => 'Voici les statistiques de paiement.',
                    'success'         => true,
                ];
            }
            if ($this->containsAny($transcript, ['ai stats', 'statistiques ia', 'show ai stats', 'intelligence artificielle'])) {
                return [
                    'action'          => 'scroll_to',
                    'url'             => null,
                    'target'          => '#aiStatsSection',
                    'spoken_response' => 'Voici les statistiques de l\'intelligence artificielle.',
                    'success'         => true,
                ];
            }
        }

        // ── Universal actions (any page) ─────────────────────────────
        
        // Universal Payment/Booking Trigger (Expanded Vocab)
        if ($this->containsAny($transcript, ['payer', 'pay now', 'proceed to payment', 'confirmer le paiement', 'je veux payer', 'régler la commande', 'checkout', 'book', 'book it', 'réserver'])) {
            return [
                'action'          => 'trigger_payment',
                'url'             => null,
                'spoken_response' => 'Lancement de la procédure de réservation ou de paiement.',
                'success'         => true,
            ];
        }

        // Accessibility: Screen Reader / Guide
        if ($this->containsAny($transcript, ['décris', 'describe', 'qu\'est-ce qu\'il y a', 'lis la page', 'read the page', 'guide me', 'guide-moi', 'aveugle', 'blind', 'que vois-tu'])) {
            return [
                'action'          => 'describe_screen',
                'url'             => null,
                'spoken_response' => 'Analyse de l\'écran en cours.',
                'success'         => true,
            ];
        }

        if ($this->containsAny($transcript, ['go back', 'retour', 'précédent', 'back'])) {
            return [
                'action'          => 'go_back',
                'url'             => null,
                'spoken_response' => 'Retour à la page précédente.',
                'success'         => true,
            ];
        }

        if ($this->containsAny($transcript, ['help', 'aide', 'what can you do', 'tu peux faire quoi', 'commands', 'commandes'])) {
            return [
                'action'          => 'speak_only',
                'url'             => null,
                'spoken_response' => 'Je peux naviguer dans GoVibe, chercher des vols, hôtels, lancer un paiement, et plus encore. Essayez : voir les vols, hôtel pas cher, ou mes réservations.',
                'success'         => true,
            ];
        }

        if ($this->containsAny($transcript, ['stop', 'arrête', 'tais-toi', 'silence', 'shut up'])) {
            return [
                'action'          => 'stop_speaking',
                'url'             => null,
                'spoken_response' => null,
                'success'         => true,
            ];
        }

        if ($this->containsAny($transcript, ['merci', 'thank you', 'thanks', 'parfait', 'super'])) {
            return [
                'action'          => 'speak_only',
                'url'             => null,
                'spoken_response' => 'Avec plaisir ! N\'hésitez pas si vous avez besoin d\'autre chose.',
                'success'         => true,
            ];
        }

        return null; // No match — let AI handle it
    }

    private function extractFlightDestination(string $transcript): ?string
    {
        $patterns = [
            '/(?:vol|flight|vols|aller|voyager|partir)\s+(?:à|a|vers|pour|to)\s+([a-zàâéèêëïîôùûüç\s\-]+)/u',
            '/(?:destination|direction)\s+([a-zàâéèêëïîôùûüç\s\-]+)/u',
            '/(?:flights?\s+to)\s+([a-z\s\-]+)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $transcript, $matches)) {
                $dest = trim($matches[1]);
                // Remove trailing common words
                $dest = preg_replace('/\s+(s\'il|please|maintenant|now|today|demain).*$/iu', '', $dest);
                if (mb_strlen($dest) >= 2 && mb_strlen($dest) <= 50) {
                    return $dest;
                }
            }
        }

        return null;
    }

    private function extractCity(string $transcript, array $entityKeywords): ?string
    {
        foreach ($entityKeywords as $keyword) {
            $patterns = [
                '/' . preg_quote($keyword, '/') . '\s+(?:à|a|en|in|at)\s+([a-zàâéèêëïîôùûüç\s\-]+)/iu',
                '/(?:à|a|en|in)\s+([a-zàâéèêëïîôùûüç\s\-]+)\s+' . preg_quote($keyword, '/') . '/iu',
            ];
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $transcript, $matches)) {
                    $city = trim($matches[1]);
                    if (mb_strlen($city) >= 2 && mb_strlen($city) <= 50) {
                        return $city;
                    }
                }
            }
        }

        return null;
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function extractVoiceReply(array $data): string
    {
        $execution = $data['execution'] ?? [];
        return $execution['response']
            ?? $execution['message']
            ?? $execution['output']
            ?? $execution['result']
            ?? 'Commande traitée.';
    }

    private function errorResponse(string $message): JsonResponse
    {
        return $this->json([
            'action'          => 'error',
            'url'             => null,
            'spoken_response' => $message,
            'success'         => false,
        ], 400);
    }
}
