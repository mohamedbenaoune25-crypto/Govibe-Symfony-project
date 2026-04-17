<?php

namespace App\Controller\Api;

use App\Entity\Activite;
use App\Domain\Checkout\Entity\Checkout;
use App\Domain\Flight\Entity\Vol;
use App\Entity\Hotel;
use App\Entity\Location;
use App\Entity\Personne;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    #[Route('/api/search', name: 'app_api_search', methods: ['GET'])]
    public function search(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (strlen($query) < 2) {
            return $this->json(['results' => []]);
        }

        $results = [];
        $likeQuery = '%' . $query . '%';

        // ── Flights ─────────────────────────────────────────────
        $vols = $entityManager->getRepository(Vol::class)->createQueryBuilder('v')
            ->where('v.destination LIKE :query OR v.departureAirport LIKE :query OR v.airline LIKE :query')
            ->setParameter('query', $likeQuery)
            ->setMaxResults(4)
            ->getQuery()
            ->getResult();

        foreach ($vols as $vol) {
            $results[] = [
                'type' => 'vol',
                'category' => 'Vols',
                'title' => $vol->getDepartureAirport() . ' → ' . $vol->getDestination(),
                'subtitle' => $vol->getAirline() . ' · ' . $vol->getPrix() . ' DT',
                'url' => $this->generateUrl('app_vols_index', ['destination' => $vol->getDestination()]),
                'icon' => 'flight',
                'badge' => 'Vol',
                'badgeColor' => '#10b981',
            ];
        }

        // ── Hotels ──────────────────────────────────────────────
        $hotels = $entityManager->getRepository(Hotel::class)->createQueryBuilder('h')
            ->where('h.nom LIKE :query OR h.ville LIKE :query')
            ->setParameter('query', $likeQuery)
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        foreach ($hotels as $hotel) {
            $results[] = [
                'type' => 'hotel',
                'category' => 'Hôtels',
                'title' => $hotel->getNom(),
                'subtitle' => 'Hôtel à ' . $hotel->getVille(),
                'url' => $this->generateUrl('app_hotel_index', ['ville' => $hotel->getVille()]),
                'icon' => 'hotel',
                'badge' => 'Hôtel',
                'badgeColor' => '#8b5cf6',
            ];
        }

        // ── Cars (Location) ─────────────────────────────────────
        $voitures = $entityManager->getRepository(Location::class)->createQueryBuilder('l')
            ->leftJoin('l.voiture', 'v')
            ->where('v.marque LIKE :query OR v.modele LIKE :query OR l.lieuPrise LIKE :query')
            ->setParameter('query', $likeQuery)
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        foreach ($voitures as $location) {
            $voiture = $location->getVoiture();
            $marque = $voiture ? $voiture->getMarque() : 'Voiture';
            $modele = $voiture ? $voiture->getModele() : '';
            $results[] = [
                'type' => 'voiture',
                'category' => 'Voitures',
                'title' => $marque . ' ' . $modele,
                'subtitle' => 'Location à ' . $location->getLieuPrise(),
                'url' => $this->generateUrl('app_location_index'),
                'icon' => 'directions_car',
                'badge' => 'Voiture',
                'badgeColor' => '#f59e0b',
            ];
        }

        // ── Activities ──────────────────────────────────────────
        $activites = $entityManager->getRepository(Activite::class)->createQueryBuilder('a')
            ->where('a.Titre LIKE :query OR a.Lieu LIKE :query OR a.Categorie LIKE :query')
            ->setParameter('query', $likeQuery)
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        foreach ($activites as $activite) {
            $results[] = [
                'type' => 'activite',
                'category' => 'Activités',
                'title' => $activite->getTitre(),
                'subtitle' => $activite->getCategorie() . ' · ' . $activite->getLieu(),
                'url' => $this->generateUrl('app_activite_index'),
                'icon' => 'explore',
                'badge' => 'Activité',
                'badgeColor' => '#ec4899',
            ];
        }

        // ── Checkouts (for authenticated users) ─────────────────
        if ($this->getUser()) {
            $checkouts = $entityManager->getRepository(Checkout::class)->createQueryBuilder('c')
                ->leftJoin('c.flight', 'cf')
                ->where('c.passengerName LIKE :query OR c.passengerEmail LIKE :query OR cf.destination LIKE :query OR cf.departureAirport LIKE :query')
                ->setParameter('query', $likeQuery)
                ->setMaxResults(3)
                ->getQuery()
                ->getResult();

            foreach ($checkouts as $checkout) {
                $results[] = [
                    'type' => 'checkout',
                    'category' => 'Réservations',
                    'title' => 'Réservation #' . $checkout->getCheckoutId(),
                    'subtitle' => $checkout->getPassengerName() . ' · ' . $checkout->getTotalPrix() . ' DT',
                    'url' => $this->generateUrl('app_checkout_show', ['checkoutId' => $checkout->getCheckoutId()]),
                    'icon' => 'confirmation_number',
                    'badge' => 'Booking',
                    'badgeColor' => '#2563eb',
                ];
            }
        }

        // ── Users (admin only) ──────────────────────────────────
        if ($this->isGranted('ROLE_ADMIN')) {
            $users = $entityManager->getRepository(Personne::class)->createQueryBuilder('u')
                ->where('u.prenom LIKE :query OR u.nom LIKE :query OR u.email LIKE :query')
                ->setParameter('query', $likeQuery)
                ->setMaxResults(3)
                ->getQuery()
                ->getResult();

            foreach ($users as $user) {
                $results[] = [
                    'type' => 'user',
                    'category' => 'Utilisateurs',
                    'title' => $user->getPrenom() . ' ' . $user->getNom(),
                    'subtitle' => $user->getEmail(),
                    'url' => $this->generateUrl('app_admin_users'),
                    'icon' => 'person',
                    'badge' => 'User',
                    'badgeColor' => '#64748b',
                ];
            }
        }

        return $this->json(['results' => $results]);
    }
}
