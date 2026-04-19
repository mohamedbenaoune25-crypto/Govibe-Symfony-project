<?php

namespace App\Controller\Admin;

use App\Entity\Reservation;
use App\Repository\HotelRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reservations')]
#[IsGranted('ROLE_ADMIN')]
class AdminReservationController extends AbstractController
{
    private const ALLOWED_STATUS = ['EN_ATTENTE', 'CONFIRMEE', 'ANNULEE'];

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

    #[Route('/{id}/status', name: 'app_admin_reservations_update_status', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function updateStatus(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('reservation_status_'.$reservation->getId(), $token)) {
            $this->addFlash('error', 'Requête invalide.');

            return $this->redirectToRoute('app_admin_reservations_index', [], Response::HTTP_SEE_OTHER);
        }

        $newStatus = strtoupper((string) $request->request->get('statut', ''));
        if (!in_array($newStatus, self::ALLOWED_STATUS, true)) {
            $this->addFlash('error', 'Statut non valide.');
        } else {
            $reservation->setStatut($newStatus);
            $entityManager->flush();
            $this->addFlash('success', 'Statut mis à jour.');
        }

        $redirectTo = (string) $request->request->get('redirect_to', '');
        if ($redirectTo !== '') {
            return $this->redirect($redirectTo, Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_admin_reservations_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit-status', name: 'app_admin_reservations_edit_status', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function editStatus(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token', '');
            if (!$this->isCsrfTokenValid('reservation_edit_status_'.$reservation->getId(), $token)) {
                $this->addFlash('error', 'Requête invalide.');

                return $this->redirectToRoute('app_admin_reservations_edit_status', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
            }

            $newStatus = strtoupper((string) $request->request->get('statut', ''));
            if (!in_array($newStatus, self::ALLOWED_STATUS, true)) {
                return $this->render('admin/reservation/edit_status.html.twig', [
                    'reservation' => $reservation,
                    'status_choices' => self::ALLOWED_STATUS,
                    'redirect_to' => (string) $request->request->get('redirect_to', ''),
                    'selected_status' => $newStatus,
                    'status_error' => 'Statut non valide.',
                ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
            } else {
                $reservation->setStatut($newStatus);
                $entityManager->flush();
                $this->addFlash('success', 'Statut mis à jour.');
            }

            $redirectTo = (string) $request->request->get('redirect_to', '');
            if ($redirectTo !== '') {
                return $this->redirect($redirectTo, Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('app_admin_reservations_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/reservation/edit_status.html.twig', [
            'reservation' => $reservation,
            'status_choices' => self::ALLOWED_STATUS,
            'redirect_to' => (string) $request->query->get('redirect_to', ''),
            'selected_status' => $reservation->getStatut(),
            'status_error' => null,
        ]);
    }
}