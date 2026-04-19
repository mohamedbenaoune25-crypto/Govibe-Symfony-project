<?php

namespace App\Controller\Web;

use App\Entity\ActiviteSession;
use App\Entity\ReservationSession;
use App\Repository\ReservationSessionRepository;
use App\Repository\PersonneRepository;
use App\Repository\CouponRepository;
use App\Service\PricingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/reservation-session')]
#[IsGranted('ROLE_USER')]
class ReservationSessionController extends AbstractController
{
    private PricingService $pricingService;

    public function __construct(PricingService $pricingService)
    {
        $this->pricingService = $pricingService;
    }

    #[Route('/', name: 'app_reservation_session_index', methods: ['GET'])]
    public function index(ReservationSessionRepository $resRepo): Response
    {
        $userRef = $this->getUser() ? $this->getUser()->getUserIdentifier() : 'GUEST';
        
        return $this->render('reservation_session/index.html.twig', [
            'reservations' => $resRepo->findBy(['userRef' => $userRef], ['reservedAt' => 'DESC']),
        ]);
    }

    #[Route('/booking/{idSession}', name: 'app_reservation_session_booking', methods: ['GET'])]
    public function booking(ActiviteSession $session, PersonneRepository $userRepo): Response
    {
        /** @var \App\Entity\Personne|null $user */
        $user = $userRepo->findOneBy(['email' => $this->getUser()->getUserIdentifier()]);
        
        $pricing = $this->pricingService->calculatePrice($session, $user);

        return $this->render('reservation_session/booking.html.twig', [
            'session' => $session,
            'activite' => $session->getActivite(),
            'pricing' => $pricing,
            'user' => $user
        ]);
    }

    #[Route('/reserve/{idSession}', name: 'app_reservation_session_reserve', methods: ['POST'])]
    public function reserve(Request $request, ActiviteSession $session, EntityManagerInterface $entityManager, PersonneRepository $userRepo, CouponRepository $couponRepo): Response
    {
        $nbPlaces = (int) $request->request->get('nbPlaces', 1);
        $couponCode = $request->request->get('couponCode');
        $useCredits = $request->request->get('useCredits') === 'on';

        if ($nbPlaces < 1) $nbPlaces = 1;

        /** @var \App\Entity\Personne|null $user */
        $user = $userRepo->findOneBy(['email' => $this->getUser()->getUserIdentifier()]);
        
        $coupon = $couponCode ? $couponRepo->findActiveByCode($couponCode) : null;
        $pricing = $this->pricingService->calculatePrice($session, $user, $coupon);

        $reservation = new ReservationSession();
        $reservation->setSession($session);
        $reservation->setNbPlaces($nbPlaces);
        $reservation->setPaidAmount((string) ($pricing['finalPrice'] * $nbPlaces));
        $reservation->setUserRef($user->getEmail());

        // Handle Credits Payment
        if ($useCredits && $user && $user->getSessionCredits() >= $nbPlaces) {
            $user->setSessionCredits($user->getSessionCredits() - $nbPlaces);
            $reservation->setPaidAmount('0.00'); // Paid via credits
            $this->addFlash('success', 'Utilisation de ' . $nbPlaces . ' crédits pour votre réservation.');
        }

        if ($session->canReserve($nbPlaces)) {
            $session->reservePlaces($nbPlaces);
            $reservation->setStatus('confirmed');
            $this->addFlash('success', 'Réservation confirmée pour "' . $session->getActivite()->getName() . '".');
        } else {
            $reservation->setStatus('waiting');
            $this->addFlash('info', 'Session complète. Vous avez été placé(e) en liste d\'attente.');
        }

        $entityManager->persist($reservation);
        $entityManager->flush();

        return $this->redirectToRoute('app_reservation_session_index');
    }

    #[Route('/cancel/{idReservation}', name: 'app_reservation_session_cancel', methods: ['POST'])]
    public function cancel(Request $request, ReservationSession $reservation, EntityManagerInterface $entityManager, ReservationSessionRepository $resRepo): Response
    {
        if ($this->isCsrfTokenValid('cancel'.$reservation->getIdReservation(), $request->request->get('_token'))) {
            $session = $reservation->getSession();
            $status = $reservation->getStatus();
            $placesLiberated = $reservation->getNbPlaces();

            if ($status === 'confirmed') {
                $sessionDate = $session->getDate();
                $sessionTime = $session->getHeure();
                $sessionDateTime = new \DateTime($sessionDate->format('Y-m-d') . ' ' . $sessionTime->format('H:i:s'));
                
                $now = new \DateTime();
                $limit = (clone $sessionDateTime)->modify('-24 hours');

                if ($now > $limit) {
                    $this->addFlash('danger', 'Annulation tardive : Délai des 24h dépassé.');
                    return $this->redirectToRoute('app_reservation_session_index');
                }
            }

            $entityManager->remove($reservation);
            $entityManager->flush(); 

            if ($status === 'confirmed') {
                $session->setNbrPlacesRestant($session->getNbrPlacesRestant() + $placesLiberated);
                $this->promoteWaitlist($session, $entityManager, $resRepo);
                $entityManager->flush();
                $this->addFlash('warning', 'Réservation annulée.');
            } else {
                $this->addFlash('warning', 'File d\'attente quittée.');
            }
        }

        return $this->redirectToRoute('app_reservation_session_index');
    }

    #[Route('/mark-absent/{idReservation}', name: 'app_reservation_session_mark_absent', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function markAbsent(ReservationSession $reservation, EntityManagerInterface $em, PersonneRepository $userRepo): Response
    {
        if ($reservation->isAbsent()) {
            $this->addFlash('info', 'Déjà marqué absent.');
            return $this->redirectToRoute('app_activite_session_show', ['idSession' => $reservation->getSession()->getIdSession()]);
        }

        $reservation->setIsAbsent(true);
        $user = $userRepo->findOneBy(['email' => $reservation->getUserRef()]);
        if ($user) {
            $user->incrementAbsenceCount();
            if ($user->getAbsenceCount() >= 3) {
                $user->setIsAccountLocked(true);
                $user->setLockoutUntil((new \DateTime())->modify('+7 days'));
                $this->addFlash('danger', 'Compte bloqué (3 absences).');
            }
        }

        $em->flush();
        return $this->redirectToRoute('app_activite_session_show', ['idSession' => $reservation->getSession()->getIdSession()]);
    }

    private function promoteWaitlist(ActiviteSession $session, EntityManagerInterface $em, ReservationSessionRepository $repo): void
    {
        while ($session->getNbrPlacesRestant() > 0) {
            $waiting = $repo->findFirstWaiting($session);
            if (!$waiting) break;

            if ($session->canReserve($waiting->getNbPlaces())) {
                $session->reservePlaces($waiting->getNbPlaces());
                $waiting->setStatus('confirmed');
            } else {
                break;
            }
        }
    }

    #[Route('/pdf', name: 'app_reservation_session_pdf', methods: ['GET'])]
    public function generatePdf(ReservationSessionRepository $resRepo): Response
    {
        $userRef = $this->getUser() ? $this->getUser()->getUserIdentifier() : 'USER001';
        $reservations = $resRepo->findBy(['userRef' => $userRef]);

        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('reservation_session/pdf.html.twig', [
            'reservations' => $reservations,
            'user' => $this->getUser()
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="mes_reservations.pdf"'
        ]);
    }

    #[Route('/stats', name: 'app_reservation_session_stats', methods: ['GET'])]
    public function viewStats(ReservationSessionRepository $resRepo): Response
    {
        $userRef = $this->getUser() ? $this->getUser()->getUserIdentifier() : 'USER001';

        $totalReservations = $resRepo->countReservationsByUser($userRef);
        $totalParticipants = $resRepo->countTotalParticipantsByUser($userRef);
        $popularActivities = $resRepo->getMostReservedActivitiesByUser($userRef);

        return $this->render('reservation_session/stats.html.twig', [
            'totalReservations' => $totalReservations,
            'totalParticipants' => $totalParticipants,
            'popularActivities' => $popularActivities,
        ]);
    }

    #[Route('/edit/{idReservation}', name: 'app_reservation_session_edit', methods: ['GET'])]
    public function edit(ReservationSession $reservation): Response
    {
        return $this->render('reservation_session/edit.html.twig', [
            'reservation' => $reservation,
            'session' => $reservation->getSession(),
            'activite' => $reservation->getSession()->getActivite(),
        ]);
    }

    #[Route('/update/{idReservation}', name: 'app_reservation_session_update', methods: ['POST'])]
    public function update(Request $request, ReservationSession $reservation, EntityManagerInterface $entityManager): Response
    {
        $newNbPlaces = (int) $request->request->get('nbPlaces', 1);
        if ($newNbPlaces < 1) $newNbPlaces = 1;

        $session = $reservation->getSession();
        $oldNbPlaces = $reservation->getNbPlaces();

        try {
            if ($reservation->getStatus() === 'confirmed') {
                $session->adjustPlaces($oldNbPlaces, $newNbPlaces);
            }
            $reservation->setNbPlaces($newNbPlaces);
            $entityManager->flush();
            $this->addFlash('success', 'Mise à jour réussie.');
        } catch (\LogicException $e) {
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_reservation_session_index');
    }
}
