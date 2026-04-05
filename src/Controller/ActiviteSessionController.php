<?php

namespace App\Controller;

use App\Entity\Activite;
use App\Entity\ActiviteSession;
use App\Form\ActiviteSessionType;
use App\Repository\ActiviteSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/session')]
final class ActiviteSessionController extends AbstractController
{
    /**
     * Liste toutes les sessions (optionnellement filtrées par activité)
     */
    #[Route(name: 'app_session_index', methods: ['GET'])]
    public function index(
        ActiviteSessionRepository $repo,
        Request $request
    ): Response {
        $activiteId = $request->query->get('activite');
        $sessions   = $activiteId
            ? $repo->findBy(['activite' => $activiteId], ['date' => 'ASC', 'heure' => 'ASC'])
            : $repo->findBy([], ['date' => 'ASC', 'heure' => 'ASC']);

        return $this->render('session/index.html.twig', [
            'sessions'   => $sessions,
            'activiteId' => $activiteId,
        ]);
    }

    /**
     * Créer une nouvelle session
     * Supporte un paramètre ?activite=ID pour pré-sélectionner l'activité
     */
    #[Route('/new', name: 'app_session_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $session = new ActiviteSession();

        // Pré-sélectionner une activité si passée en query param
        $activiteId = $request->query->get('activite');
        if ($activiteId) {
            $activite = $em->find(Activite::class, $activiteId);
            if ($activite) {
                $session->setActivite($activite);
            }
        }

        $form = $this->createForm(ActiviteSessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Auto-sync: places restantes = capacité à la création si non rempli
            if ($session->getNbrPlacesRestant() === null) {
                $session->setNbrPlacesRestant($session->getCapacite());
            }

            $em->persist($session);
            $em->flush();

            $this->addFlash('success', 'Session créée avec succès !');

            // Retour vers l'activité si on venait de la page show
            $referer = $request->query->get('redirect');
            if ($referer === 'activite' && $session->getActivite()) {
                return $this->redirectToRoute('app_activite_show', [
                    'id' => $session->getActivite()->getId(),
                ]);
            }

            return $this->redirectToRoute('app_session_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('session/new.html.twig', [
            'session' => $session,
            'form'    => $form,
        ]);
    }

    /**
     * Voir le détail d'une session avec ses réservations
     */
    #[Route('/{id}', name: 'app_session_show', methods: ['GET'])]
    public function show(ActiviteSession $session): Response
    {
        return $this->render('session/show.html.twig', [
            'session' => $session,
        ]);
    }

    /**
     * Modifier une session existante
     */
    #[Route('/{id}/edit', name: 'app_session_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        ActiviteSession $session,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(ActiviteSessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Session modifiée avec succès !');
            return $this->redirectToRoute('app_session_show', [
                'id' => $session->getIdSession(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->render('session/edit.html.twig', [
            'session' => $session,
            'form'    => $form,
        ]);
    }

    /**
     * Supprimer une session
     */
    #[Route('/{id}', name: 'app_session_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        ActiviteSession $session,
        EntityManagerInterface $em
    ): Response {
        $activite = $session->getActivite();

        if ($this->isCsrfTokenValid('delete_session' . $session->getIdSession(), $request->getPayload()->getString('_token'))) {
            $em->remove($session);
            $em->flush();
            $this->addFlash('success', 'Session supprimée avec succès.');
        }

        // Rediriger vers la page de l'activité parente si possible
        if ($activite) {
            return $this->redirectToRoute('app_activite_show', [
                'id' => $activite->getId(),
            ], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_session_index', [], Response::HTTP_SEE_OTHER);
    }
}
