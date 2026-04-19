<?php
namespace App\Controller\Web;

use App\Entity\ActiviteSession;
use App\Form\ActiviteSessionType;
use App\Repository\ActiviteSessionRepository;
use App\Repository\ReservationSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activite-session')]
#[IsGranted('ROLE_USER')]
class ActiviteSessionController extends AbstractController
{
    /**
     * LIST: Admin sees all sessions, regular user sees only their own.
     */
    #[Route('/', name: 'app_activite_session_index', methods: ['GET'])]
    public function index(ActiviteSessionRepository $sessionRepository): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            $sessions = $sessionRepository->findAllSortedByActiviteName();
        } else {
            $sessions = $sessionRepository->findByUser($this->getUser());
        }

        return $this->render('activite_session/index.html.twig', [
            'sessions' => $sessions,
        ]);
    }

    /**
     * CREATE: Any authenticated user can create a session (auto-assigned as owner).
     */
    #[Route('/new', name: 'app_activite_session_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $session = new ActiviteSession();
        $form = $this->createForm(ActiviteSessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Automatically assign the current user as the owner
            $session->setCreatedBy($this->getUser());

            $entityManager->persist($session);
            $entityManager->flush();

            $this->addFlash('success', 'La session a été programmée.');
            return $this->redirectToRoute('app_activite_session_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('activite_session/new.html.twig', [
            'session' => $session,
            'form'    => $form,
        ]);
    }

    /**
     * SHOW: Anyone can view a session detail.
     */
    #[Route('/{idSession}', name: 'app_activite_session_show', methods: ['GET'])]
    public function show(ActiviteSession $session, ReservationSessionRepository $resRepo): Response
    {
        return $this->render('activite_session/show.html.twig', [
            'session' => $session,
            'reservations' => $resRepo->findBy(['session' => $session], ['status' => 'ASC', 'reservedAt' => 'ASC']),
        ]);
    }

    /**
     * EDIT: Only the owner or admin can edit.
     */
    #[Route('/{idSession}/edit', name: 'app_activite_session_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ActiviteSession $session, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessOwnerOrAdmin($session);

        $form = $this->createForm(ActiviteSessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La session a été mise à jour.');
            return $this->redirectToRoute('app_activite_session_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('activite_session/edit.html.twig', [
            'session' => $session,
            'form'    => $form,
        ]);
    }

    /**
     * DELETE: Only the owner or admin can delete.
     */
    #[Route('/{idSession}', name: 'app_activite_session_delete', methods: ['POST'])]
    public function delete(Request $request, ActiviteSession $session, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessOwnerOrAdmin($session);

        if ($this->isCsrfTokenValid('delete'.$session->getIdSession(), $request->request->get('_token'))) {
            $entityManager->remove($session);
            $entityManager->flush();
            $this->addFlash('danger', 'La session a été annulée.');
        }

        return $this->redirectToRoute('app_activite_session_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Helper: throws 403 if the current user is neither the owner nor an admin.
     */
    private function denyAccessUnlessOwnerOrAdmin(ActiviteSession $session): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return; // Admin can do anything
        }

        $owner = $session->getCreatedBy();

        // If the session has no owner (legacy data), allow any user to edit
        if ($owner === null) {
            return;
        }

        /** @var \App\Entity\Personne $currentUser */
        $currentUser = $this->getUser();

        if ($owner->getId() !== $currentUser->getId()) {
            throw $this->createAccessDeniedException(
                'Vous n\'êtes pas autorisé à modifier cette session.'
            );
        }
    }
}
