<?php
namespace App\Controller\Web;

use App\Entity\Activite;
use App\Form\ActiviteType;
use App\Repository\ActiviteRepository;
use App\Repository\PersonneRepository;
use App\Service\RecommendationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activite')]
#[IsGranted('ROLE_USER')]
class ActiviteController extends AbstractController
{
    #[Route('/', name: 'app_activite_index', methods: ['GET'])]
    public function index(Request $request, ActiviteRepository $activiteRepository, RecommendationService $recommendationService, PersonneRepository $userRepo): Response
    {
        $query = $request->query->get('q');
        $sortBy = $request->query->get('sort', 'rank');
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $statusFilter = $isAdmin ? null : 'Confirmed';

        /** @var \App\Entity\Personne|null $user */
        $user = $userRepo->findOneBy(['email' => $this->getUser()->getUserIdentifier()]);

        if ($query) {
            $activites = $activiteRepository->findBySearch($query, $statusFilter);
        } else {
            $activites = $isAdmin 
                ? $activiteRepository->findAll() 
                : $activiteRepository->findOptimized($user ? $user->getResidenceCity() : null, $sortBy);
        }

        $recommendations = [];
        $trending = [];

        if (!$isAdmin && !$query) {
            if ($user) {
                $recommendations = $recommendationService->getRecommendations($user);
            }
            $trending = $activiteRepository->findTrending(3);
        }

        return $this->render('activite/index.html.twig', [
            'activites' => $activites,
            'recommendations' => $recommendations,
            'trending' => $trending,
            'search_query' => $query,
            'current_sort' => $sortBy
        ]);
    }

    #[Route('/new', name: 'app_activite_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $activite = new Activite();
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Si l'utilisateur n'est pas Admin, l'activité est 'Pending' par défaut
            if (!$this->isGranted('ROLE_ADMIN')) {
                $activite->setStatus('Pending');
            }
            
            $entityManager->persist($activite);
            $entityManager->flush();

            $this->addFlash('success', 'L\'activité a été proposée avec succès.');
            return $this->redirectToRoute('app_activite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('activite/new.html.twig', [
            'activite' => $activite,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_activite_show', methods: ['GET'])]
    public function show(Activite $activite): Response
    {
        return $this->render('activite/show.html.twig', [
            'activite' => $activite,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_activite_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Activite $activite, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'L\'activité a été mise à jour.');
            return $this->redirectToRoute('app_activite_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->renderForm('activite/edit.html.twig', [
            'activite' => $activite,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_activite_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Activite $activite, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$activite->getId(), $request->request->get('_token'))) {
            $entityManager->remove($activite);
            $entityManager->flush();
            $this->addFlash('danger', 'L\'activité a été supprimée.');
        }

        return $this->redirectToRoute('app_activite_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/validate', name: 'app_activite_validate', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function validate(Activite $activite, EntityManagerInterface $entityManager): Response
    {
        $activite->setStatus('Confirmed');
        $entityManager->flush();

        $this->addFlash('success', 'L\'activité "' . $activite->getName() . '" a été validée.');
        return $this->redirectToRoute('app_activite_index');
    }

    #[Route('/{id}/reject', name: 'app_activite_reject', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function reject(Activite $activite, EntityManagerInterface $entityManager): Response
    {
        $activite->setStatus('Rejected');
        $entityManager->flush();

        $this->addFlash('warning', 'L\'activité "' . $activite->getName() . '" a été rejetée.');
        return $this->redirectToRoute('app_activite_index');
    }
}
