<?php

namespace App\Controller;

use App\Entity\Hotel;
use App\Entity\Reservation;
use App\Form\HotelType;
use App\Form\ReservationType;
use App\Repository\HotelRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/hotel')]
class HotelController extends AbstractController
{
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

        if ($form->isSubmitted()) {
            $this->validateHotelInput($hotel, $form);

            if ($form->isValid()) {
                $entityManager->persist($hotel);
                $entityManager->flush();

                return $this->redirectToRoute('app_hotel_index', [], Response::HTTP_SEE_OTHER);
            }
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
                $this->validateReservationInput($reservation, $reservationForm);

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

        if ($form->isSubmitted()) {
            $this->validateHotelInput($hotel, $form);

            if ($form->isValid()) {
                $entityManager->flush();

                return $this->redirectToRoute('app_hotel_index', [], Response::HTTP_SEE_OTHER);
            }
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

    private function validateHotelInput(Hotel $hotel, FormInterface $form): void
    {
        if (trim((string) $hotel->getNom()) === '') {
            $form->get('nom')->addError(new FormError("Le nom de l'hotel est obligatoire."));
        }

        if (trim((string) $hotel->getAdresse()) === '') {
            $form->get('adresse')->addError(new FormError("L'adresse est obligatoire."));
        }

        if (trim((string) $hotel->getVille()) === '') {
            $form->get('ville')->addError(new FormError('La ville est obligatoire.'));
        }

        if ($hotel->getNombreEtoiles() !== null && ($hotel->getNombreEtoiles() < 1 || $hotel->getNombreEtoiles() > 5)) {
            $form->get('nombreEtoiles')->addError(new FormError("Le nombre d'etoiles doit etre compris entre 1 et 5."));
        }

        if ($hotel->getBudget() !== null && $hotel->getBudget() < 0) {
            $form->get('budget')->addError(new FormError('Le budget doit etre positif ou nul.'));
        }

        if ($hotel->getPhotoUrl() !== null && trim($hotel->getPhotoUrl()) !== '' && filter_var($hotel->getPhotoUrl(), FILTER_VALIDATE_URL) === false) {
            $form->get('photoUrl')->addError(new FormError('Veuillez saisir une URL valide.'));
        }
    }

    private function validateReservationInput(Reservation $reservation, FormInterface $form): void
    {
        if (!$reservation->getDateDebut() instanceof \DateTimeInterface) {
            $form->get('dateDebut')->addError(new FormError('La date de debut est obligatoire et doit etre valide.'));
        }

        if (!$reservation->getDateFin() instanceof \DateTimeInterface) {
            $form->get('dateFin')->addError(new FormError('La date de fin est obligatoire et doit etre valide.'));
        }

        if ($reservation->getDateDebut() instanceof \DateTimeInterface && $reservation->getDateFin() instanceof \DateTimeInterface && $reservation->getDateFin() <= $reservation->getDateDebut()) {
            $form->get('dateFin')->addError(new FormError('La date de fin doit etre posterieure a la date de debut.'));
        }

        if ($reservation->getPrixTotal() === null || !is_numeric((string) $reservation->getPrixTotal()) || $reservation->getPrixTotal() <= 0) {
            $form->get('prixTotal')->addError(new FormError('Le prix total doit etre un nombre superieur a 0.'));
        }

        if ($form->has('statut') && trim((string) $reservation->getStatut()) === '') {
            $form->get('statut')->addError(new FormError('Le statut est obligatoire.'));
        }

        if ($reservation->getChambre() === null) {
            $form->get('chambre')->addError(new FormError('La chambre est obligatoire.'));
        }
    }
}