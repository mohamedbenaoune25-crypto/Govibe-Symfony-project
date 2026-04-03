<?php

namespace App\Controller;

use App\Entity\PasswordResets;
use App\Entity\Personne;
use App\Form\ForgotPasswordType;
use App\Form\ResetPasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class PasswordResetController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function request(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer, LoggerInterface $logger): Response
    {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            // Vérifie si l'utilisateur existe
            $user = $entityManager->getRepository(Personne::class)->findOneBy(['email' => $email]);

            if ($user) {
                // Génération d'un code alphanumérique à 6 caractères
                $token = substr(str_shuffle(str_repeat('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', 3)), 0, 6);

                // Sauvegarde du token avec expiration dans 15 min
                $passwordReset = new PasswordResets();
                $passwordReset->setEmail($email);
                $passwordReset->setToken($token);
                $passwordReset->setExpirationDate((new \DateTime())->modify('+15 minutes'));

                $entityManager->persist($passwordReset);
                $entityManager->flush();

                // Envoi de l'email
                $emailMessage = (new Email())
                    ->from('noreply@govibe.com')
                    ->to($email)
                    ->subject('Votre code de réinitialisation GoVibe')
                    ->html('<p>Voici votre code de réinitialisation : <strong>' . $token . '</strong>. Il expire dans 15 minutes.</p>');

                try {
                    $mailer->send($emailMessage);
                } catch (\Exception $e) {
                    // Ignorer les erreurs d'envoi si le mailer n'est pas conf
                }

                $this->addFlash('success', 'Si un compte existe, un code a été envoyé à cette adresse.');
                return $this->redirectToRoute('app_reset_password', ['email' => $email]);
            } else {
                // Pour ne pas révéler l'existence ou non d'un compte, on affiche le même message
                $this->addFlash('success', 'Si un compte existe, un code a été envoyé à cette adresse.');
                return $this->redirectToRoute('app_reset_password', ['email' => $email]);
            }
        }

        return $this->render('security/forgot_password.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    #[Route('/reset-password', name: 'app_reset_password')]
    public function reset(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $email = $request->query->get('email');
        
        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $tokenInput = $form->get('token')->getData();
            $newPassword = $form->get('plainPassword')->getData();

            $passwordReset = $entityManager->getRepository(PasswordResets::class)->findOneBy([
                'email' => $email,
                'token' => $tokenInput
            ], ['id' => 'DESC']); // Prendre le dernier token par précaution

            if (!$passwordReset) {
                $this->addFlash('error', 'Le code renseigné est invalide ou ne correspond pas.');
            } elseif ($passwordReset->getExpirationDate() < new \DateTime()) {
                $this->addFlash('error', 'Le code a expiré.');
            } else {
                // Le code est bon, on met à jour le mot de passe
                $user = $entityManager->getRepository(Personne::class)->findOneBy(['email' => $email]);
                
                if ($user) {
                    $user->setPassword(
                        $userPasswordHasher->hashPassword($user, $newPassword)
                    );
                    $entityManager->flush();

                    // Nettoyer les tokens de l'utilisateur
                    $qb = $entityManager->createQueryBuilder();
                    $qb->delete(PasswordResets::class, 'pr')
                       ->where('pr.email = :email')
                       ->setParameter('email', $email)
                       ->getQuery()
                       ->execute();

                    $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès.');
                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form->createView(),
            'email' => $email
        ]);
    }
}
