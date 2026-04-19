<?php
namespace App\Controller\Web;

use App\Entity\ActiviteSession;
use App\Form\ActiviteSessionType;
use App\Repository\ActiviteSessionRepository;
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
    #[Route('/', name: 'app_activite_session_index', methods: ['GET'])]
    public function index(ActiviteSessionRepository $sessionRepository): Response
    {
        return $this->render('activite_session/index.html.twig', [
            'sessions' => $sessionRepository->findAllSortedByActiviteName(),
        ]);
    }

    #[Route('/new', name: 'app_activite_session_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $session = new ActiviteSession();
        $form = $this->createForm(ActiviteSessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($session);
            $entityManager->flush();

            $this->addFlash('success', 'La session a été programmée.');
            return $this->redirectToRoute('app_activite_session_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('activite_session/new.html.twig', [
            'session' => $session,
            'form' => $form,
        ]);
    }

    #[Route('/{idSession}', name: 'app_activite_session_show', methods: ['GET'])]
    public function show(ActiviteSession $session): Response
    {
        return $this->render('activite_session/show.html.twig', [
            'session' => $session,
        ]);
    }

    #[Route('/{idSession}/edit', name: 'app_activite_session_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ActiviteSession $session, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ActiviteSessionType::class, $session);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'La session a été mise à jour.');
            return $this->redirectToRoute('app_activite_session_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('activite_session/edit.html.twig', [
            'session' => $session,
            'form' => $form,
        ]);
    }

    #[Route('/{idSession}', name: 'app_activite_session_delete', methods: ['POST'])]
    public function delete(Request $request, ActiviteSession $session, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$session->getIdSession(), $request->request->get('_token'))) {
            $entityManager->remove($session);
            $entityManager->flush();
            $this->addFlash('danger', 'La session a été annulée.');
        }

        return $this->redirectToRoute('app_activite_session_index', [], Response::HTTP_SEE_OTHER);
    }
}
