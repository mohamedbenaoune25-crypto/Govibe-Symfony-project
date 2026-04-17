<?php

namespace App\Controller\Web;

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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use App\Service\HolidayService;

#[Route('/reservation')]
#[IsGranted('ROLE_USER')]
class ReservationController extends AbstractController
{
    private HolidayService $holidayService;

    public function __construct(HolidayService $holidayService)
    {
        $this->holidayService = $holidayService;
    }

    #[Route('/holidays-api', name: 'app_reservation_holidays_api', methods: ['GET'])]
    public function holidaysApi(): Response
    {
        return $this->json([
            'holidays' => array_keys($this->holidayService->getHolidays()),
            'supplementPercentage' => $this->holidayService->getSupplementPercentage(),
        ]);
    }

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

            $this->applyCalculatedTotal($reservation);

            $this->validateReservationInput($reservation, $form);

            if ($form->isValid()) {
                $entityManager->persist($reservation);
                $entityManager->flush();

                return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('reservation/new.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
            'selectedHotel' => $selectedHotel,
        ]);
    }

    #[Route('/{id}/pay', name: 'app_reservation_pay', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function pay(Request $request, Reservation $reservation): Response
    {
        $sessionUserId = $this->syncReservationSessionUser($request);
        if ($sessionUserId === null || $reservation->getUser()?->getId() !== $sessionUserId) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('pay'.$reservation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Requete invalide.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($reservation->getStatut() !== 'EN_ATTENTE') {
            $this->addFlash('warning', 'Le paiement est autorise uniquement pour les reservations en attente.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        }

        $secretKey = (string) ($_SERVER['STRIPE_SECRET_KEY'] ?? '');
        if ($secretKey === '') {
            $this->addFlash('error', 'La cle Stripe de test est manquante. Configurez STRIPE_SECRET_KEY.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($reservation->getPrixTotal() === null || $reservation->getPrixTotal() <= 0) {
            $this->addFlash('error', 'Prix total invalide pour le paiement.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        }

        $amountCents = (int) round($reservation->getPrixTotal() * 100);

        try {
            Stripe::setApiKey($secretKey);

            $successUrl = $this->generateUrl('app_reservation_payment_success', [
                'id' => $reservation->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}';

            $cancelUrl = $this->generateUrl('app_reservation_payment_cancel', [
                'id' => $reservation->getId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $checkoutSession = Session::create([
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'unit_amount' => $amountCents,
                        'product_data' => [
                            'name' => sprintf('Reservation #%d', $reservation->getId()),
                            'description' => sprintf('Hotel: %s - Chambre: %s', (string) ($reservation->getHotel()?->getNom() ?? '-'), (string) ($reservation->getChambre()?->getType() ?? '-')),
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'metadata' => [
                    'reservation_id' => (string) $reservation->getId(),
                    'user_id' => (string) $sessionUserId,
                ],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ]);
        } catch (ApiErrorException) {
            $this->addFlash('error', 'Erreur Stripe pendant la creation de la session de paiement.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->redirect((string) $checkoutSession->url, Response::HTTP_SEE_OTHER);
    }

    #[Route('/payment/success/{id}', name: 'app_reservation_payment_success', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function paymentSuccess(Request $request, Reservation $reservation, EntityManagerInterface $entityManager): Response
    {
        $sessionUserId = $this->syncReservationSessionUser($request);
        if ($sessionUserId === null || $reservation->getUser()?->getId() !== $sessionUserId) {
            throw $this->createAccessDeniedException();
        }

        $sessionId = (string) $request->query->get('session_id', '');
        if ($sessionId === '') {
            $this->addFlash('error', 'Session de paiement manquante.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        }

        $secretKey = (string) ($_SERVER['STRIPE_SECRET_KEY'] ?? '');
        if ($secretKey === '') {
            $this->addFlash('error', 'La cle Stripe de test est manquante.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        }

        try {
            Stripe::setApiKey($secretKey);
            $checkoutSession = Session::retrieve($sessionId);
        } catch (ApiErrorException $e) {
            $this->addFlash('error', 'Erreur API Stripe: ' . $e->getMessage());
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la verification Stripe: ' . $e->getMessage());
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        }

        $paid = (string) ($checkoutSession->payment_status ?? '') === 'paid';
        $metaReservationId = (int) (($checkoutSession->metadata['reservation_id'] ?? 0));
        $metaUserId = (int) (($checkoutSession->metadata['user_id'] ?? 0));

        if (!$paid || $metaReservationId !== $reservation->getId() || $metaUserId !== $sessionUserId) {
            $this->addFlash('warning', 'Paiement non valide ou non verifie.');
            return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($reservation->getStatut() === 'EN_ATTENTE') {
            $reservation->setStatut('CONFIRMEE');
            $entityManager->flush();
            $this->addFlash('success', 'Paiement effectue. Reservation confirmee.');
        }

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/payment/cancel/{id}', name: 'app_reservation_payment_cancel', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function paymentCancel(Request $request, Reservation $reservation): Response
    {
        $sessionUserId = $this->syncReservationSessionUser($request);
        if ($sessionUserId === null || $reservation->getUser()?->getId() !== $sessionUserId) {
            throw $this->createAccessDeniedException();
        }

        $this->addFlash('info', 'Paiement annule. Reservation toujours en attente.');

        return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_reservation_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
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

    #[Route('/{id}/edit', name: 'app_reservation_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
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
            $this->applyCalculatedTotal($reservation);
            $this->validateReservationInput($reservation, $form);

            if ($form->isValid()) {
                $entityManager->flush();

                return $this->redirectToRoute('app_reservation_show', ['id' => $reservation->getId()], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('reservation/edit.html.twig', [
            'reservation' => $reservation,
            'form' => $form,
            'delete_form' => $deleteForm->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_reservation_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
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

        if ($form->has('statut') && trim((string) $reservation->getStatut()) === '') {
            $form->get('statut')->addError(new FormError('Le statut est obligatoire.'));
        }

        if ($reservation->getChambre() === null) {
            $form->get('chambre')->addError(new FormError('La chambre est obligatoire.'));
        }

        if ($reservation->getChambre() !== null && $reservation->getHotel() !== null && $reservation->getChambre()?->getHotel()?->getId() !== $reservation->getHotel()?->getId()) {
            $form->get('chambre')->addError(new FormError('La chambre selectionnee ne correspond pas a l\'hotel choisi.'));
        }

        if ($reservation->getPrixTotal() === null || $reservation->getPrixTotal() <= 0) {
            if ($reservation->getChambre() === null) {
                $form->get('chambre')->addError(new FormError('Impossible de calculer le prix total sans chambre.'));
            } else {
                $form->get('chambre')->addError(new FormError('La chambre selectionnee ne contient pas de prix valide.'));
            }

            if (!$reservation->getDateDebut() instanceof \DateTimeInterface || !$reservation->getDateFin() instanceof \DateTimeInterface) {
                $form->addError(new FormError('Le prix total est calcule automatiquement apres selection des dates.'));
            }
        }
    }

    private function applyCalculatedTotal(Reservation $reservation): void
    {
        $chambre = $reservation->getChambre();
        if ($chambre === null) {
            $reservation->setPrixTotal(null);
            return;
        }

        // Force consistency: the hotel always follows the selected room.
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