<?php

namespace App\Controller;

use App\Entity\Checkout;
use App\Repository\CheckoutRepository;
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

        $qb = $checkoutRepository->createQueryBuilder('c')
            ->leftJoin('c.flight', 'f')->addSelect('f')
            ->leftJoin('c.user', 'u')->addSelect('u')
            ->orderBy('c.reservationDate', 'DESC');

        if ($status && $status !== 'all') {
            $qb->andWhere('c.statusReservation = :status')
                ->setParameter('status', $status);
        }

        return $this->render('admin/checkouts/index.html.twig', [
            'checkouts' => $qb->getQuery()->getResult(),
            'currentStatus' => $status ?? 'all',
            'pendingCount' => $checkoutRepository->count(['statusReservation' => 'EN_ATTENTE']),
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
