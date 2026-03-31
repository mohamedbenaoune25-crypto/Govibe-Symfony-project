<?php

namespace App\Controller;

use App\Entity\Forum;
use App\Entity\MembreForum;
use App\Entity\Poste;
use App\Form\PosteType;
use App\Repository\MembreForumRepository;
use App\Repository\PosteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/postes')]
class PosteController extends AbstractController
{
    #[Route('/', name: 'app_poste_index', methods: ['GET'])]
    public function index(PosteRepository $posteRepository): Response
    {
        // Display only global posts (those without a forum)
        $postes = $posteRepository->findBy(['forum' => null], ['dateCreation' => 'DESC']);

        return $this->render('poste/index.html.twig', [
            'postes' => $postes,
            'title' => 'Community Feed'
        ]);
    }



    #[Route('/new/{forumId?}', name: 'app_poste_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager, MembreForumRepository $mfr, ?Forum $forumId = null): Response
    {
        $user = $this->getUser();
        
        // Check if posting in a forum
        if ($forumId) {
            $isMember = (bool) $mfr->findOneBy(['forum' => $forumId, 'user' => $user]);
            if (!$isMember && !$this->isGranted('ROLE_ADMIN')) {
                // If requested via AJAX, return a snippet
                if ($request->isXmlHttpRequest()) {
                    return new Response('<div class="alert alert-danger m-4">Vous devez rejoindre ce forum pour y publier.</div>');
                }
                $this->addFlash('error', 'Vous devez rejoindre ce forum pour y publier.');
                return $this->redirectToRoute('app_forum_show', ['forumId' => $forumId->getForumId()]);
            }
        }

        $poste = new Poste();
        $poste->setUser($user);
        
        if ($forumId) {
            $poste->setForum($forumId);
        }

        $form = $this->createForm(PosteType::class, $poste, [
            'action' => $this->generateUrl('app_poste_new', $forumId ? ['forumId' => $forumId->getForumId()] : []),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($poste->getType() === 'MEDIA') {
                if ($imageFile) {
                    $newFilename = uniqid().'.'.$imageFile->getClientOriginalExtension();
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/postes',
                        $newFilename
                    );
                    $poste->setUrl('/uploads/postes/'.$newFilename);
                }
            } else {
                $poste->setUrl(null);
            }

            $poste->setDateCreation(new \DateTime());
            $poste->setDateModification(new \DateTime());
            $entityManager->persist($poste);
            $entityManager->flush();

            if ($forumId) {
                return $this->redirectToRoute('app_forum_show', ['forumId' => $forumId->getForumId()], Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('app_poste_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('poste/_modal_form.html.twig', [
            'poste' => $poste,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{postId}', name: 'app_poste_show', methods: ['GET'])]
    public function show(Poste $poste): Response
    {
        return $this->render('poste/show.html.twig', [
            'poste' => $poste,
        ]);
    }

    #[Route('/{postId}/edit', name: 'app_poste_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, Poste $poste, EntityManagerInterface $entityManager): Response
    {
        // Allow only the owner or an admin to edit
        if ($this->getUser() !== $poste->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You are not allowed to edit this post.');
        }

        $form = $this->createForm(PosteType::class, $poste, [
            'action' => $this->generateUrl('app_poste_edit', ['postId' => $poste->getPostId()]),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($poste->getType() === 'MEDIA') {
                if ($imageFile) {
                    $newFilename = uniqid().'.'.$imageFile->getClientOriginalExtension();
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/uploads/postes',
                        $newFilename
                    );
                    $poste->setUrl('/uploads/postes/'.$newFilename);
                }
            } else {
                // If it's a STATUS post, clear any existing media URL
                $poste->setUrl(null);
            }

            $poste->setDateModification(new \DateTime());
            $entityManager->flush();

            if ($poste->getForum()) {
                return $this->redirectToRoute('app_forum_show', ['forumId' => $poste->getForum()->getForumId()], Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('app_poste_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('poste/_modal_form.html.twig', [
            'poste' => $poste,
            'form' => $form->createView(),
        ]);
    }



    #[Route('/{postId}', name: 'app_poste_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, Poste $poste, EntityManagerInterface $entityManager): Response
    {
        // Allow only the owner or an admin to delete
        if ($this->getUser() !== $poste->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You are not allowed to delete this post.');
        }

        $forumId = $poste->getForum() ? $poste->getForum()->getForumId() : null;

        if ($this->isCsrfTokenValid('delete'.$poste->getPostId(), $request->request->get('_token'))) {
            $entityManager->remove($poste);
            $entityManager->flush();
        }

        if ($forumId) {
            return $this->redirectToRoute('app_forum_show', ['forumId' => $forumId], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_poste_index', [], Response::HTTP_SEE_OTHER);
    }
}
