<?php

namespace App\Controller\Web;

use App\Domain\Checkout\Entity\Checkout;
use App\Domain\Checkout\Form\CheckoutType;
use App\Domain\Checkout\Repository\CheckoutRepository;
use App\Domain\Flight\Entity\Vol;
use App\Domain\Flight\Repository\VolRepository;
use App\Entity\Personne;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/checkouts')]
#[IsGranted('ROLE_USER')]
class CheckoutController extends AbstractController
{
    private string $stripeSecretKey;
    private string $stripePublicKey;

    public function __construct(ParameterBagInterface $params)
    {
        $this->stripeSecretKey = $params->get('stripe_secret_key');
        $this->stripePublicKey = $params->get('stripe_public_key');
    }
    #[Route('/', name: 'app_checkout_index', methods: ['GET'])]
    public function index(Request $request, CheckoutRepository $checkoutRepository): Response
    {
        $status = $request->query->get('status');
        $search = $request->query->get('q');

        $qb = $checkoutRepository->createQueryBuilder('c')
            ->leftJoin('c.flight', 'f')->addSelect('f')
            ->where('c.user = :user')
            ->setParameter('user', $this->getUser())
            ->orderBy('c.reservationDate', 'DESC');

        if ($status && $status !== 'all') {
            $qb->andWhere('c.statusReservation = :status')
                ->setParameter('status', $status);
        }

        if ($search) {
            if (is_numeric($search)) {
                $qb->andWhere('f.destination LIKE :search OR f.departureAirport LIKE :search OR c.statusReservation LIKE :search OR c.checkoutId = :exact')
                   ->setParameter('search', '%' . $search . '%')
                   ->setParameter('exact', $search);
            } else {
                $qb->andWhere('f.destination LIKE :search OR f.departureAirport LIKE :search OR c.statusReservation LIKE :search')
                   ->setParameter('search', '%' . $search . '%');
            }
        }

        return $this->render('checkout/index.html.twig', [
            'checkouts' => $qb->getQuery()->getResult(),
            'currentStatus' => $status ?? 'all',
            'currentSearch' => $search,
        ]);
    }

    #[Route('/new', name: 'app_checkout_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, VolRepository $volRepository): Response
    {
        $checkout = new Checkout();
        $checkout->setReservationDate(new \DateTime());
        $checkout->setStatusReservation('EN_ATTENTE');

        $user = $this->getUser();
        if ($user instanceof Personne) {
            $checkout->setUser($user);
            $checkout->setPassengerName(trim($user->getPrenom() . ' ' . $user->getNom()));
            $checkout->setPassengerEmail($user->getEmail());
        }

        $flightId = $request->query->get('flight');
        if ($flightId) {
            $flight = $volRepository->find($flightId);
            if ($flight instanceof Vol) {
                $checkout->setFlight($flight);
                $checkout->setTravelClass($flight->getClasseChaise() ?? 'Economy');
            }
        }

        $newFormParams = $flightId ? ['flight' => $flightId] : [];
        $form = $this->createForm(CheckoutType::class, $checkout, [
            'action' => $this->generateUrl('app_checkout_new', $newFormParams),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $flight = $checkout->getFlight();
            if (!$flight) {
                $this->addFlash('error', 'Veuillez sélectionner un vol valide.');
                return $this->redirectToRoute('app_checkout_new');
            }

            $passengerCount = max(1, (int) $checkout->getPassengerNbr());
            if ($flight->getAvailableSeats() !== null && $passengerCount > $flight->getAvailableSeats()) {
                $this->addFlash('error', 'Le nombre de passagers dépasse les sièges disponibles.');
                return $this->redirectToRoute('app_checkout_new', ['flight' => $flight->getFlightId()]);
            }

            $checkout->setReservationDate(new \DateTime());
            $checkout->setStatusReservation('EN_ATTENTE');
            $checkout->setTotalPrix((int) $flight->getPrix() * $passengerCount);

            $entityManager->persist($checkout);
            $entityManager->flush();

            $this->addFlash('success', 'Votre checkout a été créé avec succès et attend confirmation.');

            return $this->redirectToRoute('app_checkout_index');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('checkout/_form_modal.html.twig', [
                'checkout' => $checkout,
                'form' => $form->createView(),
                'button_label' => 'Confirmer',
                'page_title' => 'Créer un checkout',
                'page_subtitle' => 'Réservez votre vol en quelques étapes.',
            ]);
        }

        return $this->render('checkout/new.html.twig', [
            'checkout' => $checkout,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{checkoutId}', name: 'app_checkout_show', methods: ['GET'])]
    public function show(Request $request, Checkout $checkout): Response
    {
        $this->assertCheckoutOwner($checkout);

        if ($request->isXmlHttpRequest()) {
            return $this->render('checkout/_detail_modal.html.twig', [
                'checkout' => $checkout,
            ]);
        }

        return $this->render('checkout/show.html.twig', [
            'checkout' => $checkout,
        ]);
    }

    #[Route('/{checkoutId}/edit', name: 'app_checkout_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Checkout $checkout, EntityManagerInterface $entityManager): Response
    {
        $this->assertCheckoutOwner($checkout);

        if ($checkout->getStatusReservation() && $checkout->getStatusReservation() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Seules les réservations en attente peuvent être modifiées.');
            return $this->redirectToRoute('app_checkout_index');
        }

        $form = $this->createForm(CheckoutType::class, $checkout, [
            'action' => $this->generateUrl('app_checkout_edit', ['checkoutId' => $checkout->getCheckoutId()]),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $flight = $checkout->getFlight();
            if (!$flight) {
                $this->addFlash('error', 'Veuillez sélectionner un vol valide.');
                return $this->redirectToRoute('app_checkout_edit', ['checkoutId' => $checkout->getCheckoutId()]);
            }

            $passengerCount = max(1, (int) $checkout->getPassengerNbr());
            if ($flight->getAvailableSeats() !== null && $passengerCount > $flight->getAvailableSeats()) {
                $this->addFlash('error', 'Le nombre de passagers dépasse les sièges disponibles.');
                return $this->redirectToRoute('app_checkout_edit', ['checkoutId' => $checkout->getCheckoutId()]);
            }

            $checkout->setTotalPrix((int) $flight->getPrix() * $passengerCount);
            $checkout->setReservationDate(new \DateTime());

            $entityManager->flush();

            $this->addFlash('success', 'Votre checkout a été mis à jour avec succès.');

            return $this->redirectToRoute('app_checkout_index');
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('checkout/_form_modal.html.twig', [
                'checkout' => $checkout,
                'form' => $form->createView(),
                'button_label' => 'Mettre à jour',
                'page_title' => 'Modifier le checkout',
                'page_subtitle' => 'Mettez à jour les informations de votre réservation.',
            ]);
        }

        return $this->render('checkout/edit.html.twig', [
            'checkout' => $checkout,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{checkoutId}', name: 'app_checkout_delete', methods: ['POST'])]
    public function delete(Request $request, Checkout $checkout, EntityManagerInterface $entityManager): Response
    {
        $this->assertCheckoutOwner($checkout);

        if ($this->isCsrfTokenValid('delete' . $checkout->getCheckoutId(), $request->request->get('_token'))) {
            if ($checkout->getStatusReservation() && $checkout->getStatusReservation() !== 'EN_ATTENTE') {
                $this->addFlash('error', 'Seules les réservations en attente peuvent être supprimées.');
                return $this->redirectToRoute('app_checkout_index');
            }

            $entityManager->remove($checkout);
            $entityManager->flush();
            $this->addFlash('success', 'Votre checkout a été supprimé.');
        }

        return $this->redirectToRoute('app_checkout_index');
    }

    #[Route('/{checkoutId}/stripe', name: 'app_checkout_stripe', methods: ['POST', 'GET'])]
    public function stripeSession(Checkout $checkout, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->assertCheckoutOwner($checkout);

        if ($checkout->getStatusReservation() !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Cette réservation ne peut pas être payée ou est déjà réglée.');
            return $this->redirectToRoute('app_checkout_index');
        }

        if (empty($this->stripeSecretKey)) {
            $this->addFlash('error', 'Configuration Stripe manquante. Vérifiez STRIPE_SECRET_KEY dans .env.local.');
            return $this->redirectToRoute('app_checkout_index');
        }

        \Stripe\Stripe::setApiKey($this->stripeSecretKey);
        $flight = $checkout->getFlight();
        $flightName = $flight ? ($flight->getDepartureAirport() . ' → ' . $flight->getDestination()) : 'Vol GoVibe';

        try {
            $returnUrl = $this->generateUrl('app_checkout_stripe_return', [
                'checkoutId' => $checkout->getCheckoutId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL) . '?session_id={CHECKOUT_SESSION_ID}';

            $cancelUrl = $this->generateUrl('app_checkout_stripe_cancel', [
                'checkoutId' => $checkout->getCheckoutId(),
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $sessionParams = [
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'eur',
                        'unit_amount' => $checkout->getTotalPrix() * 100,
                        'product_data' => [
                            'name' => 'Réservation #' . $checkout->getCheckoutId() . ' : ' . $flightName,
                            'description' => $checkout->getPassengerNbr() . ' passager(s) - ' . $checkout->getTravelClass(),
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'ui_mode' => 'embedded_page',
                'return_url' => $returnUrl,
                'metadata' => [
                    'checkout_id' => $checkout->getCheckoutId(),
                    'flight_id' => $flight ? $flight->getFlightId() : null,
                ],
            ];

            // Add customer email if available
            $email = $checkout->getPassengerEmail();
            if ($email) {
                $sessionParams['customer_email'] = $email;
            }

            $stripeSession = \Stripe\Checkout\Session::create($sessionParams);

            // Store Stripe session ID for verification
            $checkout->setStripeSessionId($stripeSession->id);
            $entityManager->flush();

            return $this->render('checkout/stripe_embedded.html.twig', [
                'checkout' => $checkout,
                'clientSecret' => $stripeSession->client_secret,
                'stripePublicKey' => $this->stripePublicKey,
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de l\'initialisation du paiement : ' . $e->getMessage());
            return $this->redirectToRoute('app_checkout_index');
        }
    }

    #[Route('/{checkoutId}/stripe/return', name: 'app_checkout_stripe_return')]
    public function stripeReturn(Request $request, Checkout $checkout): Response
    {
        $this->assertCheckoutOwner($checkout);
        
        $sessionId = $request->query->get('session_id');
        if (!$sessionId) {
            return $this->redirectToRoute('app_checkout_index');
        }

        \Stripe\Stripe::setApiKey($this->stripeSecretKey);
        
        try {
            $session = \Stripe\Checkout\Session::retrieve($sessionId);
            
            if ($session->payment_status === 'paid') {
                return $this->redirectToRoute('app_checkout_stripe_success', ['checkoutId' => $checkout->getCheckoutId()]);
            } else {
                return $this->redirectToRoute('app_checkout_stripe_cancel', ['checkoutId' => $checkout->getCheckoutId()]);
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la validation du paiement.');
            return $this->redirectToRoute('app_checkout_index');
        }
    }

    #[Route('/{checkoutId}/stripe/success', name: 'app_checkout_stripe_success')]
    public function stripeSuccess(Checkout $checkout, EntityManagerInterface $entityManager): Response
    {
        $this->assertCheckoutOwner($checkout);

        if ($checkout->getStatusReservation() === 'EN_ATTENTE') {
            $checkout->setStatusReservation('CONFIRMEE');
            $checkout->setPaymentMethod('STRIPE');
            $checkout->setPaidAt(new \DateTime());

            // Deduct seats from flight
            $flight = $checkout->getFlight();
            if ($flight && method_exists($flight, 'getAvailableSeats') && method_exists($flight, 'setAvailableSeats')) {
                $newSeats = max(0, $flight->getAvailableSeats() - $checkout->getPassengerNbr());
                $flight->setAvailableSeats($newSeats);
            }

            $entityManager->flush();
        }

        return $this->render('checkout/stripe_success.html.twig', [
            'checkout' => $checkout,
        ]);
    }

    #[Route('/{checkoutId}/stripe/cancel', name: 'app_checkout_stripe_cancel')]
    public function stripeCancel(Checkout $checkout): Response
    {
        $this->assertCheckoutOwner($checkout);

        return $this->render('checkout/stripe_cancel.html.twig', [
            'checkout' => $checkout,
        ]);
    }

    #[Route('/{checkoutId}/ticket/download', name: 'app_checkout_ticket_download')]
    public function downloadTicket(Checkout $checkout): Response
    {
        $this->assertCheckoutOwner($checkout);

        if ($checkout->getStatusReservation() !== 'CONFIRMEE') {
            $this->addFlash('error', 'Vous ne pouvez générer un billet que pour une réservation confirmée.');
            return $this->redirectToRoute('app_checkout_show', ['checkoutId' => $checkout->getCheckoutId()]);
        }

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Helvetica');
        $pdfOptions->setIsRemoteEnabled(true);

        $dompdf = new Dompdf($pdfOptions);
        $html = $this->renderView('checkout/ticket_pdf.html.twig', [
            'checkout' => $checkout,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response(
            $dompdf->output(),
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="billet_govibe_'.$checkout->getCheckoutId().'.pdf"'
            ]
        );
    }


    #[Route('/{checkoutId}/signature/save', name: 'app_checkout_signature_save', methods: ['POST'])]
    public function saveSignature(Checkout $checkout, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->assertCheckoutOwner($checkout);
        
        $data = json_decode($request->getContent(), true);
        $signature = $data['signature'] ?? null;

        if (!$signature) {
            return new JsonResponse(['error' => 'Signature invalide.'], 400);
        }

        $checkout->setSignature($signature);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    private function assertCheckoutOwner(Checkout $checkout): void
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof Personne || !$checkout->getUser() || $checkout->getUser()->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez consulter que vos propres checkouts.');
        }
    }
}