<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\HotelRepository;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservation')]
#[IsGranted('ROLE_USER')]
class ReservationController extends AbstractController
{
    #[Route('/', name: 'app_reservation_index', methods: ['GET'])]
    public function index(ReservationRepository $reservationRepository, Request $request): Response
    {
        $user = $this->getUser();
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sortBy', 'dateDebut');
        $sortDir = $request->query->get('sortDir', 'DESC');

        $validSortFields = ['dateDebut', 'dateFin', 'prixTotal', 'statut', 'createdAt'];
        if (!in_array($sortBy, $validSortFields, true)) {
            $sortBy = 'dateDebut';
        }
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $queryBuilder = $reservationRepository->createQueryBuilder('r')
            ->leftJoin('r.user', 'u')
            ->leftJoin('r.chambre', 'c')
            ->leftJoin('r.hotel', 'h')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.' . $sortBy, $sortDir);

        if ($search) {
            $queryBuilder->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR c.type LIKE :search OR h.nom LIKE :search OR r.statut LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $reservations = $queryBuilder->getQuery()->getResult();

        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations,
            'search' => $search,
            'sortBy' => $sortBy,
            'sortDir' => $sortDir,
        ]);
    }

    #[Route('/new', name: 'app_reservation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, HotelRepository $hotelRepository): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_reservations_index', [], Response::HTTP_SEE_OTHER);
        }

        $hotelId = $request->query->getInt('hotel', 0);
        $selectedHotel = $hotelId > 0 ? $hotelRepository->find($hotelId) : null;

        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation, [
            'hotel' => $selectedHotel,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reservation->setUser($this->getUser());
            if ($selectedHotel) {
                $reservation->setHotel($selectedHotel);
                $reservation->setStatut('EN_ATTENTE');
            }
            $entityManager->persist($reservation);
            $entityManager->flush();

            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
            'selectedHotel' => $selectedHotel,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Reservation $reservation): Response
    {
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $deleteForm = $this->createFormBuilder([], [
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'delete'.$reservation->getId(),
        ])
            ->setAction($this->generateUrl('app_reservation_delete', ['id' => $reservation->getId()]))
            ->setMethod('POST')
            ->getForm();

        return $this->render('reservation/show.html.twig', [
            'reservation' => $reservation,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reservation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ReservationType::class, $reservation);
        $form->handleRequest($request);

        $deleteForm = $this->createFormBuilder([], [
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'delete'.$reservation->getId(),
        ])
            ->setAction($this->generateUrl('app_reservation_delete', ['id' => $reservation->getId()]))
            ->setMethod('POST')
            ->getForm();

        if ($form->isSubmitted() && $form->isValid()) {
            $reservation->setUser($this->getUser());
            $entityManager->flush();

            return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_delete', methods: ['POST'])]
    public function delete(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        if ($reservation->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$reservation->getId(), $request->request->get('_token'))) {
            $redirectTo = $request->request->get('redirect_to');
            $entityManager->remove($reservation);
            $entityManager->flush();

            if (is_string($redirectTo) && $redirectTo !== '') {
                return $this->redirect($redirectTo, Response::HTTP_SEE_OTHER);
            }
        }

        return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
    }
}