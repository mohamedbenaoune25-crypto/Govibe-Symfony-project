<?php

namespace App\Controller;

use App\Entity\ReservationSession;
use App\Form\ReservationSessionType;
use App\Repository\ReservationSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/reservation-session')]
final class ReservationSessionController extends AbstractController
{
    /**
     * Liste toutes les réservations de session
     */
    #[Route(name: 'app_reservation_session_index', methods: ['GET'])]
    public function index(ReservationSessionRepository $repo): Response
    {
        return $this->render('reservation_session/index.html.twig', [
            'reservations' => $repo->findBy([], ['reservedAt' => 'DESC']),
        ]);
    }

    /**
     * Créer une nouvelle réservation
     */
    #[Route('/new', name: 'app_reservation_session_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $reservation = new ReservationSession();
        $form        = $this->createForm(ReservationSessionType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session   = $reservation->getSession();
            $nbPlaces  = $reservation->getNbPlaces() ?? 1;

            // Vérifier disponibilité
            if ($session && $session->getNbrPlacesRestant() < $nbPlaces) {
                $this->addFlash('danger', sprintf(
                    'Pas assez de places disponibles. Il reste %d place(s).',
                    $session->getNbrPlacesRestant()
                ));
                return $this->render('reservation_session/new.html.twig', [
                    'reservation' => $reservation,
                    'form'        => $form,
                ]);
            }

            // Décrémenter les places restantes
            if ($session) {
                $session->setNbrPlacesRestant(
                    $session->getNbrPlacesRestant() - $nbPlaces
                );
            }

            $em->persist($reservation);
            $em->flush();

            $this->addFlash('success', 'Réservation créée avec succès !');
            return $this->redirectToRoute('app_reservation_session_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation_session/new.html.twig', [
            'reservation' => $reservation,
            'form'        => $form,
        ]);
    }

    /**
     * Voir le détail d'une réservation
     */
    #[Route('/{id}', name: 'app_reservation_session_show', methods: ['GET'])]
    public function show(ReservationSession $reservation): Response
    {
        return $this->render('reservation_session/show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    /**
     * Modifier une réservation existante
     */
    #[Route('/{id}/edit', name: 'app_reservation_session_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        ReservationSession $reservation,
        EntityManagerInterface $em
    ): Response {
        $ancienNb      = $reservation->getNbPlaces() ?? 1;
        $ancienSession = $reservation->getSession();

        $form = $this->createForm(ReservationSessionType::class, $reservation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $nouvelleSession = $reservation->getSession();
            $nouveauNb       = $reservation->getNbPlaces() ?? 1;

            // Remettre les places de l'ancienne session si elle change
            if ($ancienSession && $ancienSession !== $nouvelleSession) {
                $ancienSession->setNbrPlacesRestant(
                    $ancienSession->getNbrPlacesRestant() + $ancienNb
                );
            }

            // Ajuster la différence sur la même session ou la nouvelle
            $sessionCible = $nouvelleSession ?? $ancienSession;
            if ($sessionCible) {
                $diff = $nouveauNb - ($ancienSession === $nouvelleSession ? $ancienNb : 0);
                if ($sessionCible->getNbrPlacesRestant() < $diff) {
                    $this->addFlash('danger', sprintf(
                        'Pas assez de places disponibles. Il reste %d place(s).',
                        $sessionCible->getNbrPlacesRestant()
                    ));
                    return $this->render('reservation_session/edit.html.twig', [
                        'reservation' => $reservation,
                        'form'        => $form,
                    ]);
                }
                $sessionCible->setNbrPlacesRestant(
                    $sessionCible->getNbrPlacesRestant() - $diff
                );
            }

            $em->flush();
            $this->addFlash('success', 'Réservation modifiée avec succès !');

            return $this->redirectToRoute('app_reservation_session_show', [
                'id' => $reservation->getIdReservation(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('reservation_session/edit.html.twig', [
            'reservation' => $reservation,
            'form'        => $form,
        ]);
    }

    /**
     * Supprimer une réservation (et remettre les places)
     */
    #[Route('/{id}', name: 'app_reservation_session_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        ReservationSession $reservation,
        EntityManagerInterface $em
    ): Response {
        if ($this->isCsrfTokenValid('delete_reservation' . $reservation->getIdReservation(), $request->getPayload()->getString('_token'))) {
            // Remettre les places dans la session
            $session = $reservation->getSession();
            if ($session) {
                $session->setNbrPlacesRestant(
                    $session->getNbrPlacesRestant() + ($reservation->getNbPlaces() ?? 1)
                );
            }

            $em->remove($reservation);
            $em->flush();
            $this->addFlash('success', 'Réservation annulée et places restituées.');
        }

        return $this->redirectToRoute('app_reservation_session_index', [], Response::HTTP_SEE_OTHER);
    }
    /**
     * Générer un PDF (Reçu) de la réservation
     */
    #[Route('/{id}/pdf', name: 'app_reservation_session_pdf', methods: ['GET'])]
    public function generatePdf(ReservationSession $reservation): Response
    {
        if (!class_exists(Dompdf::class)) {
            // Si Dompdf n'est pas installé, on affiche une vue imprimable propre
            return $this->render('reservation_session/pdf.html.twig', [
                'reservation' => $reservation,
                'is_printable' => true
            ]);
        }

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($options);

        $html = $this->renderView('reservation_session/pdf.html.twig', [
            'reservation' => $reservation,
            'is_printable' => false
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Recu_Reservation_'.$reservation->getIdReservation().'.pdf"'
        ]);
    }
}
