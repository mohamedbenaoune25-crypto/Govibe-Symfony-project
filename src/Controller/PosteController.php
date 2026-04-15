<?php

namespace App\Controller;

use App\Entity\Forum;
use App\Entity\MembreForum;
use App\Entity\Poste;
use App\Form\PosteType;
use App\Repository\MembreForumRepository;
use App\Repository\PosteRepository;
use App\Repository\ForumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
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
    public function index(Request $request, PosteRepository $posteRepository, ForumRepository $forumRepository, EntityManagerInterface $entityManager): Response
    {
        $query = $request->query->get('q');
        $sort = $request->query->get('sort', 'newest');
        $user = $this->getUser();
        
        // Fetch posts with search, sort and personal filter
        $postes = $posteRepository->searchAndSort($query, $sort, true, null, $user);
        
        // Fetch forums for sidebar
        $forums = $forumRepository->findAll();

        // Check for flagged content if admin (used for showing/hiding the "Attention" box)
        $flaggedCount = 0;
        if ($this->isGranted('ROLE_ADMIN')) {
            $badWords = ['con', 'salope', 'merde', 'putain', 'connard', 'encule', 'débile', 'pute', 'btch', 'fck'];
            $qb = $posteRepository->createQueryBuilder('p');
            $orX = $qb->expr()->orX();
            foreach ($badWords as $i => $word) {
                $orX->add($qb->expr()->like('p.contenu', ':word'.$i));
                $qb->setParameter('word'.$i, '%'.$word.'%');
            }
            $flaggedCount = $qb->select('count(p.postId)')->where($orX)->getQuery()->getSingleScalarResult();

            if ($flaggedCount == 0) {
                $qbC = $entityManager->getRepository(\App\Entity\Commentaire::class)->createQueryBuilder('c');
                $orXC = $qbC->expr()->orX();
                foreach ($badWords as $i => $word) {
                    $orXC->add($qbC->expr()->like('c.contenu', ':word'.$i));
                    $qbC->setParameter('word'.$i, '%'.$word.'%');
                }
                $flaggedCount = $qbC->select('count(c.commentaireId)')->where($orXC)->getQuery()->getSingleScalarResult();
            }
        }

        return $this->render('poste/index.html.twig', [
            'postes' => $postes,
            'forums' => $forums,
            'current_query' => $query,
            'current_sort' => $sort,
            'flagged_count' => $flaggedCount,
            'title' => $sort === 'mine' ? 'Mes Publications' : 'Community Feed'
        ]);
    }

    #[Route('/export/pdf', name: 'app_poste_export_pdf', methods: ['GET'])]
    public function exportPdf(PosteRepository $posteRepository): Response
    {
        $postes = $posteRepository->findBy(['forum' => null], ['dateCreation' => 'DESC']);
        
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($pdfOptions);
        
        $html = $this->renderView('poste/pdf_export.html.twig', [
            'postes' => $postes
        ]);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="publications_govibe.pdf"'
        ]);
    }

    #[Route('/export/excel', name: 'app_poste_export_excel', methods: ['GET'])]
    public function exportExcel(PosteRepository $posteRepository): Response
    {
        $postes = $posteRepository->findBy(['forum' => null], ['dateCreation' => 'DESC']);
        
        $csvData = "ID;Auteur;Contenu;Date;Likes;Commentaires\n";
        foreach ($postes as $p) {
            $auteur = $p->getUser() ? $p->getUser()->getPrenom() . ' ' . $p->getUser()->getNom() : 'Anonyme';
            $contenu = str_replace([';', "\n", "\r"], [' ', ' ', ' '], $p->getContenu());
            $csvData .= sprintf(
                "%d;%s;%s;%s;%d;%d\n",
                $p->getPostId(),
                $auteur,
                $contenu,
                $p->getDateCreation()->format('Y-m-d H:i'),
                $p->getLikes(),
                count($p->getCommentaires())
            );
        }

        return new Response($csvData, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="publications_govibe.csv"'
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
                } elseif ($request->request->get('externalImageUrl')) {
                    // Download from AI suggested URL
                    $extUrl = $request->request->get('externalImageUrl');
                    try {
                        $imgContent = file_get_contents($extUrl);
                        if ($imgContent) {
                            $ext = pathinfo(parse_url($extUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                            $newFilename = uniqid().'.'.$ext;
                            file_put_contents(
                                $this->getParameter('kernel.project_dir').'/public/uploads/postes/'.$newFilename,
                                $imgContent
                            );
                            $poste->setUrl('/uploads/postes/'.$newFilename);
                        }
                    } catch (\Exception $e) {
                        // Log error or ignore
                    }
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
                } elseif ($request->request->get('externalImageUrl')) {
                    $extUrl = $request->request->get('externalImageUrl');
                    try {
                        $imgContent = file_get_contents($extUrl);
                        if ($imgContent) {
                            $ext = pathinfo(parse_url($extUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                            $newFilename = uniqid().'.'.$ext;
                            file_put_contents(
                                $this->getParameter('kernel.project_dir').'/public/uploads/postes/'.$newFilename,
                                $imgContent
                            );
                            $poste->setUrl('/uploads/postes/'.$newFilename);
                        }
                    } catch (\Exception $e) {}
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



    #[Route('/like/{postId}', name: 'app_poste_like', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function like(Poste $poste, EntityManagerInterface $entityManager, Request $request): Response
    {
        $session = $request->getSession();
        $likedPosts = $session->get('liked_posts', []);
        
        $isAlreadyLiked = in_array($poste->getPostId(), $likedPosts);
        $poste->toggleLike($isAlreadyLiked);
        
        if ($isAlreadyLiked) {
            $likedPosts = array_diff($likedPosts, [$poste->getPostId()]);
        } else {
            $likedPosts[] = $poste->getPostId();
        }
        
        $session->set('liked_posts', $likedPosts);
        $entityManager->flush();
        
        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_poste_index');
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
