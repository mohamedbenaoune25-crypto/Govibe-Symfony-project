<?php

namespace App\Controller;

use App\Entity\Commentaire;
use App\Entity\Poste;
use App\Form\CommentaireType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/commentaire')]
class CommentaireController extends AbstractController
{
    #[Route('/new/{postId}', name: 'app_commentaire_new', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request, Poste $poste, EntityManagerInterface $entityManager): Response
    {
        $content = $request->request->get('contenu');
        $token = $request->request->get('_token');

        if ($this->isCsrfTokenValid('add_comment', $token) && !empty(trim($content))) {
            $commentaire = new Commentaire();
            $commentaire->setPoste($poste);
            $commentaire->setUser($this->getUser());
            $commentaire->setContenu($content);
            $commentaire->setStatut('publié');

            $entityManager->persist($commentaire);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_poste_index');
    }

    #[Route('/reply/{postId}/{parentId}', name: 'app_commentaire_reply', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function reply(Request $request, Poste $poste, int $parentId, EntityManagerInterface $entityManager): Response
    {
        $content = $request->request->get('contenu');
        $token = $request->request->get('_token');

        if ($this->isCsrfTokenValid('add_comment', $token) && !empty(trim($content))) {
            $commentaire = new Commentaire();
            $commentaire->setPoste($poste);
            $commentaire->setUser($this->getUser());
            $commentaire->setContenu($content);
            $commentaire->setParentId($parentId);
            $commentaire->setStatut('publié');

            $entityManager->persist($commentaire);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_poste_index');
    }

    #[Route('/like/{commentaireId}', name: 'app_commentaire_like', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function like(Commentaire $commentaire, EntityManagerInterface $entityManager): Response
    {
        $commentaire->setLikes($commentaire->getLikes() + 1);
        $entityManager->flush();

        return $this->redirectToRoute('app_poste_index');
    }

    #[Route('/dislike/{commentaireId}', name: 'app_commentaire_dislike', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function dislike(Commentaire $commentaire, EntityManagerInterface $entityManager): Response
    {
        $commentaire->setDislikes($commentaire->getDislikes() + 1);
        $entityManager->flush();

        return $this->redirectToRoute('app_poste_index');
    }

    #[Route('/edit/{commentaireId}', name: 'app_commentaire_edit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(Request $request, Commentaire $commentaire, EntityManagerInterface $entityManager): Response
    {
        // Check if user is owner or admin
        if ($this->getUser() === $commentaire->getUser() || $this->isGranted('ROLE_ADMIN')) {
            $content = $request->request->get('contenu');
            $token = $request->request->get('_token');

            if ($this->isCsrfTokenValid('edit_comment'.$commentaire->getCommentaireId(), $token) && !empty(trim($content))) {
                $commentaire->setContenu($content);
                $entityManager->flush();
            }
        }

        $referer = $request->headers->get('referer');
        return $referer ? $this->redirect($referer) : $this->redirectToRoute('app_poste_index');
    }

    #[Route('/delete/{commentaireId}', name: 'app_commentaire_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(Request $request, Commentaire $commentaire, EntityManagerInterface $entityManager): Response
    {
        // Check if user is owner or admin
        if ($this->getUser() === $commentaire->getUser() || $this->isGranted('ROLE_ADMIN')) {
            if ($this->isCsrfTokenValid('delete_comment'.$commentaire->getCommentaireId(), $request->request->get('_token'))) {
                $entityManager->remove($commentaire);
                $entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_poste_index');
    }
}
