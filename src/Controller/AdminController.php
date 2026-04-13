<?php

namespace App\Controller;

use App\Entity\Personne;
use App\Entity\Reclamation;
use App\Entity\Poste;
use App\Entity\Forum;
use App\Entity\Commentaire;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $userRepo = $entityManager->getRepository(Personne::class);
        $userCount = $userRepo->count([]);
        
        $reqCount = $entityManager->getRepository(Reclamation::class)->count([]);
        $reqAttente = $entityManager->getRepository(Reclamation::class)->count(['status' => 'EN_ATTENTE']);
        
        $latestUsers = $userRepo->findBy([], ['id' => 'DESC'], 5);
        $latestReclamations = $entityManager->getRepository(Reclamation::class)->findBy([], ['dateEnvoi' => 'DESC'], 5);

        // --- Récupérer les vraies statistiques des inscriptions pour le graphique (6 derniers mois) ---
        $allUsers = $userRepo->findAll();
        $chartLabels = [];
        $chartData = [];
        
        $frenchMonths = [
            '01' => 'Janv.', '02' => 'Févr.', '03' => 'Mars', '04' => 'Avr.', '05' => 'Mai', '06' => 'Juin',
            '07' => 'Juil.', '08' => 'Août', '09' => 'Sept.', '10' => 'Oct.', '11' => 'Nov.', '12' => 'Déc.'
        ];

        for ($i = 5; $i >= 0; $i--) {
            $d = (new \DateTime())->modify("-$i months");
            $key = $d->format('Y-m');
            $monthNum = $d->format('m');
            
            $label = $frenchMonths[$monthNum];
            
            $chartLabels[$key] = $label;
            $chartData[$key] = 0;
        }

        // Compter les vraies inscriptions
        foreach ($allUsers as $u) {
            // S'il y a un champ createdAt ou utiliser directement l'ID si on ne trouve pas
            if (method_exists($u, 'getCreatedAt') && $u->getCreatedAt() !== null) {
                $m = $u->getCreatedAt()->format('Y-m');
                if (isset($chartData[$m])) {
                    $chartData[$m]++;
                }
            }
        }

        return $this->render('admin/index.html.twig', [
            'total_users' => $userCount,
            'total_reclamations' => $reqCount,
            'attente_reclamations' => $reqAttente,
            'latest_users' => $latestUsers,
            'latest_reclamations' => $latestReclamations,
            'chart_labels' => array_values($chartLabels),
            'chart_data' => array_values($chartData)
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(EntityManagerInterface $entityManager): Response
    {
        $users = $entityManager->getRepository(Personne::class)->findAll();
        
        return $this->render('admin/users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users/{id}/role', name: 'app_admin_user_role', methods: ['POST'])]
    public function changeRole(Request $request, Personne $user, EntityManagerInterface $entityManager): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier votre propre rôle.');
            return $this->redirectToRoute('app_admin_users');
        }

        $newRole = $request->request->get('role');
        // Dans Personne.php, le rôle est une string 'user' ou 'admin'
        if (in_array($newRole, ['user', 'admin'])) {
             if (method_exists($user, 'setRole')) {
                 $user->setRole($newRole); 
             } else if (method_exists($user, 'setRoles')) {
                 $user->setRoles([strtoupper('ROLE_' . $newRole)]);
             }
             $entityManager->flush();
             $this->addFlash('success', 'Rôle mis à jour avec succès.');
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/users/{id}/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(Request $request, Personne $user, EntityManagerInterface $entityManager): Response
    {
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_admin_users');
        }

        if ($this->isCsrfTokenValid('delete' . $user->getId(), $request->request->get('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
            $this->addFlash('success', 'Utilisateur supprimé avec succès.');
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/users/{id}/edit', name: 'app_admin_user_edit', methods: ['POST'])]
    public function editUser(Request $request, Personne $user, EntityManagerInterface $entityManager): Response
    {
        $prenom = $request->request->get('prenom');
        $nom = $request->request->get('nom');
        $email = $request->request->get('email');

        if ($prenom && $nom && $email) {
            $user->setPrenom($prenom);
            $user->setNom($nom);
            $user->setEmail($email);
            
            $entityManager->flush();
            $this->addFlash('success', 'Le profil de l\'utilisateur a été modifié avec succès.');
        } else {
            $this->addFlash('error', 'Veuillez remplir tous les champs du formulaire.');
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/reclamations', name: 'app_admin_reclamations')]
    public function reclamations(EntityManagerInterface $entityManager): Response
    {
        $reclamations = $entityManager->getRepository(Reclamation::class)->findBy([], ['dateEnvoi' => 'DESC']);
        
        return $this->render('admin/reclamations.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    #[Route('/reclamations/{id}/status', name: 'app_admin_reclamation_status', methods: ['POST'])]
    public function changeStatus(Request $request, Reclamation $reclamation, EntityManagerInterface $entityManager): Response
    {
        $newStatus = $request->request->get('status');
        $reponse = $request->request->get('reponse');

        if (in_array($newStatus, ['EN_ATTENTE', 'TRAITEE', 'REFUSEE'])) {
             $reclamation->setStatus($newStatus);
             
             if (!empty($reponse)) {
                 $reclamation->setReponse($reponse);
                 // Assuming setDateReponse exists in entity (it should based on previous readings)
                 if (method_exists($reclamation, 'setDateReponse')) {
                     $reclamation->setDateReponse(new \DateTime());
                 }
             }

             $entityManager->flush();
             $this->addFlash('success', 'Réclamation mise à jour avec succès (Statut/Réponse).');
        }

        return $this->redirectToRoute('app_admin_reclamations');
    }


    #[Route('/forums_admin', name: 'app_admin_forums')]
    public function forums(EntityManagerInterface $entityManager): Response
    {
        $forums = $entityManager->getRepository(Forum::class)->findAll();
        
        return $this->render('admin/forums.html.twig', [
            'forums' => $forums,
        ]);
    }

    #[Route('/publications/{id}/delete', name: 'app_admin_poste_delete', methods: ['POST'])]
    public function deletePoste(Request $request, Poste $poste, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $poste->getPostId(), $request->request->get('_token'))) {
            $entityManager->remove($poste);
            $entityManager->flush();
            $this->addFlash('success', 'Publication supprimée avec succès.');
        }

        return $this->redirectToRoute('app_admin_attention');
    }

    #[Route('/forums_admin/{id}/delete', name: 'app_admin_forum_delete', methods: ['POST'])]
    public function deleteForum(Request $request, Forum $forum, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $forum->getForumId(), $request->request->get('_token'))) {
            $entityManager->remove($forum);
            $entityManager->flush();
            $this->addFlash('success', 'Forum supprimé avec succès.');
        }

        return $this->redirectToRoute('app_admin_forums');
    }

    #[Route('/attention', name: 'app_admin_attention')]
    public function attention(EntityManagerInterface $entityManager): Response
    {
        $badWords = ['con', 'salope', 'merde', 'putain', 'connard', 'encule', 'débile', 'pute', 'btch', 'fck'];
        
        $postes = $entityManager->getRepository(Poste::class)->findAll();
        $commentaires = $entityManager->getRepository(Commentaire::class)->findAll();
        
        $flaggedPostes = [];
        foreach ($postes as $p) {
            foreach ($badWords as $w) {
                if ($p->getContenu() && stripos($p->getContenu(), $w) !== false) {
                    $flaggedPostes[] = $p;
                    break;
                }
            }
        }
        
        $flaggedCommentaires = [];
        foreach ($commentaires as $c) {
            foreach ($badWords as $w) {
                if ($c->getContenu() && stripos($c->getContenu(), $w) !== false) {
                    $flaggedCommentaires[] = $c;
                    break;
                }
            }
        }

        return $this->render('admin/attention.html.twig', [
            'postes' => $flaggedPostes,
            'commentaires' => $flaggedCommentaires,
            'bad_words' => $badWords
        ]);
    }

    #[Route('/admin/commentaire/{id}/delete', name: 'app_admin_commentaire_delete', methods: ['POST'])]
    public function deleteCommentaire(Request $request, Commentaire $commentaire, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $commentaire->getCommentaireId(), $request->request->get('_token'))) {
            $entityManager->remove($commentaire);
            $entityManager->flush();
            $this->addFlash('success', 'Commentaire supprimé avec succès.');
        }

        return $this->redirectToRoute('app_admin_attention');
    }
}
