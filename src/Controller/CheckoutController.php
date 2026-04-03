<?php

namespace App\Controller;

use App\Entity\Checkout;
use App\Entity\Personne;
use App\Entity\Vol;
use App\Form\CheckoutType;
use App\Repository\CheckoutRepository;
use App\Repository\VolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/checkouts')]
#[IsGranted('ROLE_USER')]
class CheckoutController extends AbstractController
{
    #[Route('/', name: 'app_checkout_index', methods: ['GET'])]
    public function index(Request $request, CheckoutRepository $checkoutRepository): Response
    {
        $status = $request->query->get('status');
        $qb = $checkoutRepository->createQueryBuilder('c')
            ->leftJoin('c.flight', 'f')->addSelect('f')
            ->where('c.user = :user')
            ->setParameter('user', $this->getUser())
            ->orderBy('c.reservationDate', 'DESC');

        if ($status && $status !== 'all') {
            $qb->andWhere('c.statusReservation = :status')
                ->setParameter('status', $status);
        }

        return $this->render('checkout/index.html.twig', [
            'checkouts' => $qb->getQuery()->getResult(),
            'currentStatus' => $status ?? 'all',
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

    private function assertCheckoutOwner(Checkout $checkout): void
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof Personne || !$checkout->getUser() || $checkout->getUser()->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez consulter que vos propres checkouts.');
        }
    }
}