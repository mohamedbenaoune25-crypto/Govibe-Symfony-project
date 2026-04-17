<?php

namespace App\Controller\Admin;

use App\Domain\Checkout\Entity\Checkout;
use App\Domain\Checkout\Repository\CheckoutRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/checkouts')]
#[IsGranted('ROLE_ADMIN')]
class AdminCheckoutController extends AbstractController
{
    #[Route('/', name: 'app_admin_checkouts_index', methods: ['GET'])]
    public function index(Request $request, CheckoutRepository $checkoutRepository): Response
    {
        $status = $request->query->get('status');
        $allCheckouts = $checkoutRepository->findAll();

        $qb = $checkoutRepository->createQueryBuilder('c')
            ->leftJoin('c.flight', 'f')->addSelect('f')
            ->leftJoin('c.user', 'u')->addSelect('u')
            ->orderBy('c.reservationDate', 'DESC');

        if ($status && $status !== 'all') {
            $qb->andWhere('c.statusReservation = :status')
                ->setParameter('status', $status);
        }

        $checkouts = $qb->getQuery()->getResult();

        // Dashboard Metrics Calculation
        $stats = [
            'totalRevenue' => 0,
            'revenueToday' => 0,
            'revenueMonth' => 0,
            'totalOrders' => count($allCheckouts),
            'pendingOrders' => 0,
            'confirmedOrders' => 0,
            'rejectedOrders' => 0,
            'highRiskOrders' => 0,
            'paymentMethods' => [],
            'destinations' => []
        ];

        $today = new \DateTime('today');
        $thisMonth = new \DateTime('first day of this month');

        foreach ($allCheckouts as $c) {
            // Count Statuses
            $s = $c->getStatusReservation();
            if ($s === 'EN_ATTENTE') $stats['pendingOrders']++;
            if ($s === 'CONFIRMEE') $stats['confirmedOrders']++;
            if ($s === 'REFUSEE') $stats['rejectedOrders']++;

            // Calculate Revenue (only for confirmed or pending to be safe, or just confirmed based on Stripe)
            if ($s === 'CONFIRMEE' || !empty($c->getStripeSessionId())) {
                $price = (float) $c->getTotalPrix();
                $stats['totalRevenue'] += $price;

                $date = $c->getReservationDate();
                if ($date) {
                    if ($date >= $today) {
                        $stats['revenueToday'] += $price;
                    }
                    if ($date >= $thisMonth) {
                        $stats['revenueMonth'] += $price;
                    }
                }
            }

            // Payment Methods
            $pm = $c->getPaymentMethod() ?: 'UNKNOWN';
            $stats['paymentMethods'][$pm] = ($stats['paymentMethods'][$pm] ?? 0) + 1;

            // Destinations (assuming Flight is attached)
            $flight = $c->getFlight();
            if ($flight && $flight->getDestination()) {
                $dest = $flight->getDestination();
                $stats['destinations'][$dest] = ($stats['destinations'][$dest] ?? 0) + 1;
            }

            // Calculate Risk
            $riskScore = 0;
            if ($c->getPassengerNbr() >= 6) $riskScore += 40;
            if ($c->getTotalPrix() > 4000) $riskScore += 20;
            if ($c->getPaymentMethod() === 'CREDIT_CARD') $riskScore += 15;
            
            $resDate = $c->getReservationDate();
            if ($flight && $resDate && $flight->getDepartureTime()) {
                if ($resDate->format('Y-m-d') === $flight->getDepartureTime()->format('Y-m-d')) {
                    $riskScore += 25;
                }
            }

            if ($riskScore >= 60) {
                $stats['highRiskOrders']++;
            }
        }

        // Top Destination
        arsort($stats['destinations']);
        $stats['topDestination'] = key($stats['destinations']) ?: 'N/A';

        // Evaluate Risk for Displayed Checkouts
        foreach ($checkouts as $c) {
            $riskScore = 0;
            if ($c->getPassengerNbr() >= 6) $riskScore += 40;
            if ($c->getTotalPrix() > 4000) $riskScore += 20;
            if ($c->getPaymentMethod() === 'CREDIT_CARD') $riskScore += 15;
            
            $flight = $c->getFlight();
            $resDate = $c->getReservationDate();
            if ($flight && $resDate && $flight->getDepartureTime()) {
                if ($resDate->format('Y-m-d') === $flight->getDepartureTime()->format('Y-m-d')) {
                    $riskScore += 25;
                }
            }

            $c->riskScore = $riskScore;
            if ($riskScore >= 60) $c->riskLevel = 'HIGH';
            elseif ($riskScore >= 30) $c->riskLevel = 'MEDIUM';
            else $c->riskLevel = 'SAFE';
        }

        return $this->render('admin/checkouts/index.html.twig', [
            'checkouts' => $checkouts,
            'currentStatus' => $status ?? 'all',
            'stats' => $stats,
        ]);
    }

    #[Route('/{checkoutId}/confirm', name: 'app_admin_checkouts_confirm', methods: ['POST'])]
    public function confirm(Request $request, Checkout $checkout, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('confirm' . $checkout->getCheckoutId(), $request->request->get('_token'))) {
            $checkout->setStatusReservation('CONFIRMEE');
            $entityManager->flush();
            $this->addFlash('success', 'Le checkout a été confirmé avec succès.');
        }

        return $this->redirectToRoute('app_admin_checkouts_index');
    }

    #[Route('/{checkoutId}/reject', name: 'app_admin_checkouts_reject', methods: ['POST'])]
    public function reject(Request $request, Checkout $checkout, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('reject' . $checkout->getCheckoutId(), $request->request->get('_token'))) {
            $checkout->setStatusReservation('REFUSEE');
            $entityManager->flush();
            $this->addFlash('success', 'Le checkout a été refusé.');
        }

        return $this->redirectToRoute('app_admin_checkouts_index');
    }
}
