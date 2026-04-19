<?php

namespace App\Service;

use App\Entity\Activite;
use App\Entity\Personne;
use App\Repository\ActiviteRepository;
use App\Repository\ReservationSessionRepository;

class RecommendationService
{
    private ActiviteRepository $activiteRepo;
    private ReservationSessionRepository $reservationRepo;
    private ContextService $contextService;

    public function __construct(
        ActiviteRepository $activiteRepo, 
        ReservationSessionRepository $reservationRepo,
        ContextService $contextService
    ) {
        $this->activiteRepo = $activiteRepo;
        $this->reservationRepo = $reservationRepo;
        $this->contextService = $contextService;
    }

    /**
     * Get recommended activities for a specific user based on profile AND context.
     * 
     * @return Activite[]
     */
    public function getRecommendations(Personne $user, int $limit = 4): array
    {
        $allActivities = $this->activiteRepo->findBy(['status' => 'Confirmed']);
        $scores = [];

        // Context Data
        $currentMoment = $this->contextService->getCurrentMoment();
        $isWeekend = $this->contextService->isWeekend();
        $weather = $this->contextService->getWeather($user->getResidenceCity() ?? '');

        // 1. Get user history
        $pastReservations = $this->reservationRepo->findBy(['userRef' => $user->getEmail()]);
        $bookedTypes = [];
        foreach ($pastReservations as $res) {
            $bookedTypes[] = $res->getSession()->getActivite()->getType();
        }
        $bookedTypes = array_unique($bookedTypes);

        // 2. Calculate score for each activity
        foreach ($allActivities as $activity) {
            $score = 0;

            // --- PILIER 1: PROFIL (+80 max) ---
            // Location (+30)
            if ($user->getResidenceCity() && stripos($activity->getLocalisation(), $user->getResidenceCity()) !== false) {
                $score += 30;
            }
            // Preferences (+25)
            if ($user->getPreferredCategories() && in_array($activity->getType(), $user->getPreferredCategories())) {
                $score += 25;
            }
            // History (+25)
            if (in_array($activity->getType(), $bookedTypes)) {
                $score += 25;
            }

            // --- PILIER 2: CONTEXTE (+120 max) ---
            // Opening Status (+50) - Crucial for immediate relevance
            if ($this->contextService->isOpen($activity->getOpeningTime(), $activity->getClosingTime())) {
                $score += 50;
            }

            // Weather Match (+40)
            $aWeather = $activity->getWeatherType();
            if ($aWeather === 'both' || $aWeather === $weather) {
                $score += 40;
            }

            // Time of Day Match (+30)
            if ($activity->getBestMoment() === $currentMoment) {
                $score += 30;
            }

            $scores[$activity->getId()] = $score;
        }

        // Sort by score descending
        arsort($scores);

        // Fetch the top activities
        $recommendedIds = array_slice(array_keys($scores), 0, $limit);
        $recommendations = [];
        
        foreach ($recommendedIds as $id) {
            foreach ($allActivities as $activity) {
                if ($activity->getId() === $id && $scores[$id] > 0) {
                    // Enrich for template UI
                    $activity->tempScore = $scores[$id];
                    $activity->isCurrentlyOpen = $this->contextService->isOpen($activity->getOpeningTime(), $activity->getClosingTime());
                    $activity->weatherMatch = ($activity->getWeatherType() === 'both' || $activity->getWeatherType() === $weather);
                    $activity->timeMatch = ($activity->getBestMoment() === $currentMoment);
                    
                    $recommendations[] = $activity;
                }
            }
        }

        return $recommendations;
    }
}
