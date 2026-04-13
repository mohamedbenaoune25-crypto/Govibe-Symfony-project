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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

#[Route('/forums')]
class ForumController extends AbstractController
{
    #[Route('/', name: 'app_forum_index', methods: ['GET'])]
    public function index(Request $request, ForumRepository $forumRepository): Response
    {
        $sort = $request->query->get('sort', 'newest');
        $user = $this->getUser();
        $forums = $forumRepository->findFilteredForums($sort, $user);
        $totalMembers = $forumRepository->getTotalMemberCount();

        return $this->render('forum/index.html.twig', [
            'forums' => $forums,
            'totalMembers' => $totalMembers,
            'current_sort' => $sort,
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

            // Automatically add creator as member with ACCEPTED status
            $membre = new MembreForum();
            $membre->setForum($forum);
            $membre->setUser($this->getUser());
            $membre->setStatus('ACCEPTED');
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
        $user = $this->getUser();
        $isMember = false;
        $memberStatus = null;
        $pendingRequests = [];

        if ($user) {
            $membership = $mfr->findOneBy(['forum' => $forum, 'user' => $user]);
            if ($membership) {
                $isMember = ($membership->getStatus() === 'ACCEPTED');
                $memberStatus = $membership->getStatus();
            }

            if ($forum->getCreatedBy() === $user || $this->isGranted('ROLE_ADMIN')) {
                $pendingRequests = $mfr->findBy(['forum' => $forum, 'status' => 'PENDING']);
            }
        }

        $query = $request->query->get('q');
        $sort = $request->query->get('sort', 'newest');

        $canViewContent = !$forum->isPrivate() || $isMember || ($user && ($forum->getCreatedBy() === $user || $this->isGranted('ROLE_ADMIN')));
        
        $postes = $canViewContent 
            ? $posteRepository->searchAndSort($query, $sort, false, $forum)
            : [];

        $data = [
            'forum' => $forum,
            'postes' => $postes,
            'isMember' => $isMember,
            'memberStatus' => $memberStatus,
            'canViewContent' => $canViewContent,
            'pendingRequests' => $pendingRequests,
            'current_query' => $query,
            'current_sort' => $sort
        ];

        if ($request->isXmlHttpRequest()) {
            return $this->render('forum/_detail_modal.html.twig', $data);
        }

        return $this->render('forum/show.html.twig', $data);
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
            
            if ($forum->isPrivate()) {
                $membre->setStatus('PENDING');
                $this->addFlash('success', 'Votre demande a été envoyée au créateur du forum.');
            } else {
                $membre->setStatus('ACCEPTED');
                $forum->setNbrMembers($forum->getNbrMembers() + 1);
            }
            
            $em->persist($membre);
            $em->flush();
        }

        return $this->redirectToRoute('app_forum_show', ['forumId' => $forum->getForumId()]);
    }

    #[Route('/{forumId}/accept/{userId}', name: 'app_forum_member_accept', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function acceptMember(Forum $forum, int $userId, EntityManagerInterface $em, MembreForumRepository $mfr, MailerInterface $mailer): Response
    {
        if ($this->getUser() !== $forum->getCreatedBy() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }
    
        $membership = $mfr->findOneBy(['forum' => $forum, 'user' => $userId, 'status' => 'PENDING']);
        if ($membership) {
            $membership->setStatus('ACCEPTED');
            $forum->setNbrMembers($forum->getNbrMembers() + 1);
            $em->flush();
    
            // Envoyer un mail d'acceptation
            $user = $membership->getUser();
            $email = (new TemplatedEmail())
                ->from('no-reply@govibe.tn')
                ->to($user->getEmail())
                ->subject('Félicitations ! Votre demande d\'adhésion a été acceptée')
                ->htmlTemplate('emails/forum_accepted.html.twig')
                ->context([
                    'user' => $user,
                    'forum' => $forum
                ]);
    
            $mailer->send($email);
    
            $this->addFlash('success', 'Membre accepté et email de notification envoyé.');
        }

        return $this->redirectToRoute('app_forum_show', ['forumId' => $forum->getForumId()]);
    }

    #[Route('/{forumId}/reject/{userId}', name: 'app_forum_member_reject', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function rejectMember(Forum $forum, int $userId, EntityManagerInterface $em, MembreForumRepository $mfr): Response
    {
        if ($this->getUser() !== $forum->getCreatedBy() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $membership = $mfr->findOneBy(['forum' => $forum, 'user' => $userId, 'status' => 'PENDING']);
        if ($membership) {
            $em->remove($membership);
            $em->flush();
            $this->addFlash('info', 'Demande refusée.');
        }

        return $this->redirectToRoute('app_forum_show', ['forumId' => $forum->getForumId()]);
    }

    #[Route('/{forumId}/export/pdf', name: 'app_forum_export_pdf', methods: ['GET'])]
    public function exportPdf(Forum $forum, PosteRepository $posteRepository): Response
    {
        $postes = $posteRepository->findBy(['forum' => $forum], ['dateCreation' => 'DESC']);
        $html = $this->renderView('poste/pdf_export.html.twig', [
            'postes' => $postes,
            'title' => 'Forum : ' . $forum->getName()
        ]);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="forum_'. $forum->getForumId() .'.pdf"'
        ]);
    }

    #[Route('/{forumId}/export/excel', name: 'app_forum_export_excel', methods: ['GET'])]
    public function exportExcel(Forum $forum, PosteRepository $posteRepository): Response
    {
        $postes = $posteRepository->findBy(['forum' => $forum]);
        
        $csvData = "ID;Contenu;Date;Likes;Commentaires\n";
        foreach ($postes as $p) {
            $contenu = str_replace([';', "\n", "\r"], [' ', ' ', ' '], $p->getContenu());
            $csvData .= sprintf(
                "%d;%s;%s;%d;%d\n",
                $p->getPostId(),
                $contenu,
                $p->getDateCreation()->format('Y-m-d H:i'),
                $p->getLikes(),
                count($p->getCommentaires())
            );
        }

        $response = new Response($csvData);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="forum_'. $forum->getForumId() .'.csv"');
        
        return $response;
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
            $wasAccepted = ($membre->getStatus() === 'ACCEPTED');
            $em->remove($membre);
            // Decrement member count only if they were accepted
            if ($wasAccepted) {
                $forum->setNbrMembers(max(1, $forum->getNbrMembers() - 1));
            }
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
