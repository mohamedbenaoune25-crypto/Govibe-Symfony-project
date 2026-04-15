<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\Personne;
use App\Repository\HotelRepository;
use App\Form\ReservationType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
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
        $sessionUserId = $this->syncReservationSessionUser($request);

        if ($sessionUserId === null) {
            throw $this->createAccessDeniedException();
        }

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

        $sessionUserId = $this->syncReservationSessionUser($request);
        if ($sessionUserId === null) {
            throw $this->createAccessDeniedException();
        }

        $hotelId = $request->query->getInt('hotel', 0);
        $selectedHotel = $hotelId > 0 ? $hotelRepository->find($hotelId) : null;

        $reservation = new Reservation();
        $form = $this->createForm(ReservationType::class, $reservation, [
            'hotel' => $selectedHotel,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $reservation->setUser($this->getUser());
            $reservation->setStatut('EN_ATTENTE');
            if ($selectedHotel) {
                $reservation->setHotel($selectedHotel);
            }

            $this->validateReservationInput($reservation, $form);

            if ($form->isValid()) {
                $entityManager->persist($reservation);
                $entityManager->flush();

                return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
            'selectedHotel' => $selectedHotel,
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'])]
    public function show(Request $request, Reservation $reservation): Response
    {
        $sessionUserId = $this->syncReservationSessionUser($request);
        if ($sessionUserId === null || $reservation->getUser()?->getId() !== $sessionUserId) {
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
        $sessionUserId = $this->syncReservationSessionUser($request);
        if ($sessionUserId === null || $reservation->getUser()?->getId() !== $sessionUserId) {
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

        if ($form->isSubmitted()) {
            $reservation->setUser($this->getUser());
            $this->validateReservationInput($reservation, $form);

            if ($form->isValid()) {
                $entityManager->flush();

                return $this->redirectToRoute('app_reservation_index', [], Response::HTTP_SEE_OTHER);
            }
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
        $sessionUserId = $this->syncReservationSessionUser($request);
        if ($sessionUserId === null || $reservation->getUser()?->getId() !== $sessionUserId) {
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

    private function syncReservationSessionUser(Request $request): ?int
    {
        $user = $this->getUser();
        if (!$user instanceof Personne) {
            return null;
        }

        $userId = $user->getId();
        if (!is_int($userId)) {
            return null;
        }

        $request->getSession()->set('reservation_user_id', $userId);

        return (int) $request->getSession()->get('reservation_user_id');
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