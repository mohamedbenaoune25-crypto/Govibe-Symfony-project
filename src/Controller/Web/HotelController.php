<?php

namespace App\Controller\Web;

use App\Entity\Hotel;
use App\Entity\Reservation;
use App\Form\HotelType;
use App\Form\ReservationType;
use App\Repository\HotelRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\HolidayService;

#[Route('/hotel')]
class HotelController extends AbstractController
{
    private HolidayService $holidayService;

    public function __construct(HolidayService $holidayService)
    {
        $this->holidayService = $holidayService;
    }

    #[Route('/', name: 'app_hotel_index', methods: ['GET'])]
    public function index(Request $request, HotelRepository $hotelRepository): Response
    {
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sortBy', 'nom');
        $sortDir = $request->query->get('sortDir', 'asc');
        
        if ($search) {
            $hotels = $hotelRepository->searchHotels($search, $sortBy, $sortDir);
        } else {
            $hotels = $hotelRepository->findAllSorted($sortBy, $sortDir);
        }
        
        return $this->render('hotel/index.html.twig', [
            'hotels' => $hotels,
            'search' => $search,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    #[Route('/new', name: 'app_hotel_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $hotel = new Hotel();
        $form = $this->createForm(HotelType::class, $hotel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($hotel);
            $entityManager->flush();

            return $this->redirectToRoute('app_hotel_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('hotel/new.html.twig', [
            'hotel' => $hotel,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_hotel_show', methods: ['GET'])]
    public function show(Request $request, Hotel $hotel, EntityManagerInterface $entityManager, ReservationRepository $reservationRepository): Response
    {
        $deleteForm = $this->createFormBuilder([], [
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'delete'.$hotel->getId(),
        ])
            ->setAction($this->generateUrl('app_hotel_delete', ['id' => $hotel->getId()]))
            ->setMethod('POST')
            ->getForm();

        $reservationForm = null;
        $hotelReservations = [];

        if ($this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $reservation = new Reservation();
            $reservationForm = $this->createForm(ReservationType::class, $reservation, [
                'hotel' => $hotel,
            ]);
            $reservationForm->handleRequest($request);

            if ($reservationForm->isSubmitted()) {
                $reservation->setUser($this->getUser());
                $reservation->setHotel($hotel);
                $reservation->setStatut('EN_ATTENTE');
                if ($reservation->getHotel() === null && $reservation->getChambre()?->getHotel() !== null) {
                    $reservation->setHotel($reservation->getChambre()->getHotel());
                }
                $this->applyCalculatedTotal($reservation);

                if ($reservationForm->isValid()) {
                    $entityManager->persist($reservation);
                    $entityManager->flush();

                    return $this->redirectToRoute('app_hotel_show', ['id' => $hotel->getId()], Response::HTTP_SEE_OTHER);
                }
            }

            $hotelReservations = $reservationRepository->createQueryBuilder('r')
                ->andWhere('r.hotel = :hotel')
                ->andWhere('r.user = :user')
                ->setParameter('hotel', $hotel)
                ->setParameter('user', $this->getUser())
                ->orderBy('r.createdAt', 'DESC')
                ->getQuery()
                ->getResult();
        }

        return $this->render('hotel/show.html.twig', [
            'hotel' => $hotel,
            'delete_form' => $deleteForm->createView(),
            'reservation_form' => $reservationForm?->createView(),
            'hotel_reservations' => $hotelReservations,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_hotel_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Hotel $hotel, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(HotelType::class, $hotel);
        $form->handleRequest($request);

        $deleteForm = $this->createFormBuilder([], [
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'delete'.$hotel->getId(),
        ])
            ->setAction($this->generateUrl('app_hotel_delete', ['id' => $hotel->getId()]))
            ->setMethod('POST')
            ->getForm();

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_hotel_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('hotel/edit.html.twig', [
            'hotel' => $hotel,
            'form' => $form,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_hotel_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Hotel $hotel, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$hotel->getId(), $request->request->get('_token'))) {
            $entityManager->remove($hotel);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_hotel_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/toggle-favoris', name: 'app_hotel_toggle_favoris', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function toggleFavoris(Hotel $hotel, EntityManagerInterface $entityManager): Response
    {
        $hotel->toggleFavoris();
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'isFavoris' => $hotel->isFavoris(),
            'message' => $hotel->isFavoris() ? 'Ajouté aux favoris' : 'Retiré des favoris'
        ]);
    }

    private function applyCalculatedTotal(Reservation $reservation): void
    {
        $chambre = $reservation->getChambre();
        if ($chambre === null) {
            $reservation->setPrixTotal(null);
            return;
        }

        // Keep reservation hotel aligned with selected room.
        if ($chambre->getHotel() !== null) {
            $reservation->setHotel($chambre->getHotel());
        }

        $dateDebut = $reservation->getDateDebut();
        $dateFin = $reservation->getDateFin();
        if (!$dateDebut instanceof \DateTimeInterface || !$dateFin instanceof \DateTimeInterface || $dateFin <= $dateDebut) {
            $reservation->setPrixTotal(null);
            return;
        }

        $nights = (int) $dateDebut->diff($dateFin)->days;
        if ($nights <= 0) {
            $reservation->setPrixTotal(null);
            return;
        }

        $unitPrice = $this->resolveRoomUnitPrice($chambre);
        if ($unitPrice <= 0) {
            $reservation->setPrixTotal(null);
            return;
        }

        $basePrice = round($unitPrice * $nights, 2);

        // Ajouter supplément pour jours fériés
        $dateDebut = \DateTimeImmutable::createFromInterface($dateDebut);
        $dateFin = \DateTimeImmutable::createFromInterface($dateFin);
        $holidaySupplement = $this->holidayService->calculateHolidaySupplement($unitPrice, $dateDebut, $dateFin);

        $totalPrice = round($basePrice + $holidaySupplement, 2);
        $reservation->setPrixTotal($totalPrice);
    }

    private function resolveRoomUnitPrice(\App\Entity\Chambre $chambre): float
    {
        $standard = (float) ($chambre->getPrixStandard() ?? 0);
        if ($standard > 0) {
            return $standard;
        }

        $highSeason = (float) ($chambre->getPrixHauteSaison() ?? 0);
        if ($highSeason > 0) {
            return $highSeason;
        }

        $lowSeason = (float) ($chambre->getPrixBasseSaison() ?? 0);
        return $lowSeason > 0 ? $lowSeason : 0.0;
    }
}