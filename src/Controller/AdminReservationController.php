<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\HotelRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reservations')]
#[IsGranted('ROLE_ADMIN')]
class AdminReservationController extends AbstractController
{
    #[Route('/', name: 'app_admin_reservations_index', methods: ['GET'])]
    public function index(Request $request, ReservationRepository $reservationRepository, HotelRepository $hotelRepository): Response
    {
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sortBy', 'dateDebut');
        $sortDir = $request->query->get('sortDir', 'DESC');
        $hotelId = $request->query->getInt('hotel', 0);
        $selectedHotel = null;

        if ($hotelId > 0) {
            $selectedHotel = $hotelRepository->find($hotelId);
            if (!$selectedHotel) {
                throw $this->createNotFoundException('Hôtel introuvable.');
            }
        }

        if ($selectedHotel) {
            $reservations = $search
                ? $reservationRepository->searchReservationsByHotel($selectedHotel->getId(), $search, $sortBy, $sortDir)
                : $reservationRepository->findByHotelSorted($selectedHotel->getId(), $sortBy, $sortDir);
        } else {
            $reservations = $search
                ? $reservationRepository->searchReservations($search, $sortBy, $sortDir)
                : $reservationRepository->findAllSorted($sortBy, $sortDir);
        }

        return $this->render('admin/reservation/index.html.twig', [
            'reservations' => $reservations,
            'search' => $search,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
            'selectedHotel' => $selectedHotel,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_reservations_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        return $this->render('admin/reservation/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}