<?php

namespace App\Controller;

use App\Form\UserProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/profile')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_profile', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('newPassword')->getData();
            $currentPassword = $form->get('currentPassword')->getData();

            if ($newPassword) {
                if (!$currentPassword) {
                    $this->addFlash('error', 'Vous devez entrer votre mot de passe actuel pour changer le mot de passe.');
                    return $this->redirectToRoute('app_user_profile');
                }
                
                if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('error', 'Le mot de passe actuel est incorrect.');
                    return $this->redirectToRoute('app_user_profile');
                }

                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $this->addFlash('success', 'Votre profil et mot de passe ont été mis à jour avec succès.');
            } else {
                 $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');
            }

            $entityManager->flush();
            
            return $this->redirectToRoute('app_user_profile');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
}
