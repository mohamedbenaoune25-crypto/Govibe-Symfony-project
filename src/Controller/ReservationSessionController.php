<?php

namespace App\Controller;

use App\Entity\ActiviteSession;
use App\Entity\ReservationSession;
use App\Repository\ReservationSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reservation-session')]
#[IsGranted('ROLE_USER')]
class ReservationSessionController extends AbstractController
{
    #[Route('/', name: 'app_reservation_session_index', methods: ['GET'])]
    public function index(ReservationSessionRepository $resRepo): Response
    {
        $userRef = $this->getUser() ? $this->getUser()->getUserIdentifier() : 'GUEST';
        
        return $this->render('reservation_session/index.html.twig', [
            'reservations' => $resRepo->findBy(['userRef' => $userRef]),
        ]);
    }

    #[Route('/booking/{idSession}', name: 'app_reservation_session_booking', methods: ['GET'])]
    public function booking(ActiviteSession $session): Response
    {
        return $this->render('reservation_session/booking.html.twig', [
            'session' => $session,
            'activite' => $session->getActivite(),
        ]);
    }

    #[Route('/reserve/{idSession}', name: 'app_reservation_session_reserve', methods: ['POST'])]
    public function reserve(Request $request, ActiviteSession $session, EntityManagerInterface $entityManager): Response
    {
        $nbPlaces = (int) $request->request->get('nbPlaces', 1);
        if ($nbPlaces < 1) $nbPlaces = 1;

        if (!$session->canReserve($nbPlaces)) {
            $this->addFlash('danger', 'Désolé, il ne reste plus assez de places pour cette session.');
            return $this->redirectToRoute('app_activite_show', ['id' => $session->getActivite()->getId()]);
        }

        $reservation = new ReservationSession();
        $reservation->setSession($session);
        $reservation->setNbPlaces($nbPlaces);
        
        $userRef = $this->getUser() ? $this->getUser()->getUserIdentifier() : 'USER001';
        $reservation->setUserRef($userRef);

        $session->reservePlaces($nbPlaces);

        $entityManager->persist($reservation);
        $entityManager->flush();

        $this->addFlash('success', 'Votre réservation pour "' . $session->getActivite()->getName() . '" a été confirmée !');

        return $this->redirectToRoute('app_reservation_session_index');
    }

    #[Route('/cancel/{idReservation}', name: 'app_reservation_session_cancel', methods: ['POST'])]
    public function cancel(Request $request, ReservationSession $reservation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('cancel'.$reservation->getIdReservation(), $request->request->get('_token'))) {
            $session = $reservation->getSession();
            $session->setNbrPlacesRestant($session->getNbrPlacesRestant() + $reservation->getNbPlaces());
            
            $entityManager->remove($reservation);
            $entityManager->flush();
            
            $this->addFlash('warning', 'Votre réservation a été annulée.');
        }

        return $this->redirectToRoute('app_reservation_session_index');
    }
}
