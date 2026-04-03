<?php

namespace App\Controller;

use App\Entity\Forum;
use App\Entity\MembreForum;
use App\Form\ForumType;
use App\Repository\ForumRepository;
use App\Repository\MembreForumRepository;
use App\Repository\PosteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/forums')]
class ForumController extends AbstractController
{
    #[Route('/', name: 'app_forum_index', methods: ['GET'])]
    public function index(ForumRepository $forumRepository): Response
    {
        return $this->render('forum/index.html.twig', [
            'forums' => $forumRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_forum_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $forum = new Forum();
        $forum->setCreatedBy($this->getUser());
        
        $form = $this->createForm(ForumType::class, $forum, [
            'action' => $this->generateUrl('app_forum_new'),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $newFilename = uniqid().'.'.$imageFile->getClientOriginalExtension();
                $imageFile->move(
                    $this->getParameter('kernel.project_dir').'/public/uploads/forums',
                    $newFilename
                );
                $forum->setImage('/uploads/forums/'.$newFilename);
            }

            // Set dynamic stats
            $forum->setNbrMembers(1); // Creator is the first member
            $forum->setPostCount(0);

            $entityManager->persist($forum);

            // Automatically add creator as member
            $membre = new MembreForum();
            $membre->setForum($forum);
            $membre->setUser($this->getUser());
            $entityManager->persist($membre);

            $entityManager->flush();

            return $this->redirectToRoute('app_forum_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('forum/_modal_form.html.twig', [
            'forum' => $forum,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{forumId}', name: 'app_forum_show', methods: ['GET'])]
    public function show(Request $request, Forum $forum, PosteRepository $posteRepository, MembreForumRepository $mfr): Response
    {
        // Fetch posts belonging to this forum
        $postes = $posteRepository->findBy(['forum' => $forum], ['dateCreation' => 'DESC']);

        // Check if current user is a member
        $isMember = false;
        if ($this->getUser()) {
            $isMember = (bool) $mfr->findOneBy(['forum' => $forum, 'user' => $this->getUser()]);
        }

        if ($request->isXmlHttpRequest()) {
            return $this->render('forum/_detail_modal.html.twig', [
                'forum' => $forum,
                'postes' => $postes,
                'isMember' => $isMember,
            ]);
        }

        return $this->render('forum/show.html.twig', [
            'forum' => $forum,
            'postes' => $postes,
            'isMember' => $isMember,
        ]);
    }

    #[Route('/{forumId}/join', name: 'app_forum_join', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function join(Forum $forum, EntityManagerInterface $em, MembreForumRepository $mfr): Response
    {
        $user = $this->getUser();
        if (!$mfr->findOneBy(['forum' => $forum, 'user' => $user])) {
            $membre = new MembreForum();
            $membre->setForum($forum);
            $membre->setUser($user);
            $em->persist($membre);
            
            // Increment member count
            $forum->setNbrMembers($forum->getNbrMembers() + 1);
            $em->flush();
        }

        return $this->redirectToRoute('app_forum_show', ['forumId' => $forum->getForumId()]);
    }

    #[Route('/{forumId}/leave', name: 'app_forum_leave', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function leave(Forum $forum, EntityManagerInterface $em, MembreForumRepository $mfr): Response
    {
        $user = $this->getUser();
        
        // Creator cannot leave their own forum
        if ($forum->getCreatedBy() === $user) {
            $this->addFlash('error', 'Le créateur ne peut pas quitter le forum.');
            return $this->redirectToRoute('app_forum_show', ['forumId' => $forum->getForumId()]);
        }

        $membre = $mfr->findOneBy(['forum' => $forum, 'user' => $user]);
        if ($membre) {
            $em->remove($membre);
            // Decrement member count
            $forum->setNbrMembers(max(1, $forum->getNbrMembers() - 1));
            $em->flush();
        }

        return $this->redirectToRoute('app_forum_show', ['forumId' => $forum->getForumId()]);
    }

    #[Route('/{forumId}/edit', name: 'app_forum_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, Forum $forum, EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser() !== $forum->getCreatedBy() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ForumType::class, $forum, [
            'action' => $this->generateUrl('app_forum_edit', ['forumId' => $forum->getForumId()]),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('imageFile')->getData();

            if ($imageFile) {
                $newFilename = uniqid().'.'.$imageFile->getClientOriginalExtension();
                $imageFile->move(
                    $this->getParameter('kernel.project_dir').'/public/uploads/forums',
                    $newFilename
                );
                $forum->setImage('/uploads/forums/'.$newFilename);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_forum_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('forum/_modal_form.html.twig', [
            'forum' => $forum,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{forumId}', name: 'app_forum_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, Forum $forum, EntityManagerInterface $entityManager): Response
    {
        if ($this->getUser() !== $forum->getCreatedBy() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$forum->getForumId(), $request->request->get('_token'))) {
            $entityManager->remove($forum);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_forum_index', [], Response::HTTP_SEE_OTHER);
    }
}
