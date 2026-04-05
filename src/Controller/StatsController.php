<?php

namespace App\Controller;

use App\Repository\ActiviteRepository;
use App\Repository\ActiviteSessionRepository;
use App\Repository\ReservationSessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dash-stats')]
class StatsController extends AbstractController
{
    #[Route(name: 'app_stats_index', methods: ['GET'])]
    public function index(
        ActiviteRepository $activiteRepo,
        ActiviteSessionRepository $sessionRepo,
        ReservationSessionRepository $reservationRepo
    ): Response {
        // 1. Chiffres clés
        $totalActivites = $activiteRepo->count([]);
        $totalSessions  = $sessionRepo->count([]);
        $totalResas     = $reservationRepo->count([]);
        
        // Calcul des places totales réservées
        $allResas = $reservationRepo->findAll();
        $totalPlacesReservees = array_reduce($allResas, function($carry, $r) {
            return $carry + ($r->getNbPlaces() ?? 0);
        }, 0);

        // 2. Taux d'occupation par activité (Top 5)
        $activites = $activiteRepo->findAll();
        $statsActivites = [];
        foreach ($activites as $act) {
            $nbResasAct = 0;
            foreach ($act->getSessions() as $sess) {
                $nbResasAct += (int) $sess->getReservationSessions()->count();
            }
            $statsActivites[] = [
                'name' => $act->getName(),
                'count' => $nbResasAct
            ];
        }
        
        // Trier par popularité
        usort($statsActivites, fn($a, $b) => $b['count'] <=> $a['count']);
        $topActivites = array_slice($statsActivites, 0, 5);

        // 3. Distribution temporelle (ex: 7 derniers jours)
        // (Simplifié pour la démo: on groupe juste par date de réservation si possible)
        $resasParDate = [];
        foreach ($allResas as $r) {
            if ($r->getReservedAt()) {
                $dateStr = $r->getReservedAt()->format('d/m');
                $resasParDate[$dateStr] = ($resasParDate[$dateStr] ?? 0) + 1;
            }
        }
        // Trier les dates par ordre croissant (un peu arbitraire ici)
        ksort($resasParDate);

        return $this->render('stats/index.html.twig', [
            'totalActivites' => $totalActivites,
            'totalSessions' => $totalSessions,
            'totalResas' => $totalResas,
            'totalPlacesReservees' => $totalPlacesReservees,
            'topActivites' => $topActivites,
            'dateLabels' => array_keys($resasParDate),
            'dateValues' => array_values($resasParDate),
            'topPopLabels' => array_map(fn($a) => $a['name'], $topActivites),
            'topPopValues' => array_map(fn($a) => $a['count'], $topActivites),
        ]);
    }
}
