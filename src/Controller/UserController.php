<?php

namespace App\Controller;

use App\Entity\Personne;
use App\Form\UserProfileType;
use App\Service\FaceRecognitionService;
use App\Service\UserSessionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        UserSessionService $sessionService
    ): Response {
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

        // Get active sessions for the sessions panel
        $activeSessions = $sessionService->getActiveSessions($user);

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'active_sessions' => $activeSessions,
            'has_face_enrolled' => !empty($user->getFaceEncoding()),
        ]);
    }

    /**
     * Get active sessions as JSON (for AJAX refresh).
     */
    #[Route('/sessions', name: 'app_user_sessions', methods: ['GET'])]
    public function sessions(UserSessionService $sessionService): JsonResponse
    {
        $user = $this->getUser();
        $sessions = $sessionService->getActiveSessions($user);

        $data = array_map(function ($session) {
            return [
                'id'          => $session->getId(),
                'device'      => $session->getDeviceName(),
                'ip'          => $session->getIpAddress(),
                'country'     => $session->getCountry(),
                'city'        => $session->getCity(),
                'loginDate'   => $session->getLoginDate()->format('d/m/Y H:i'),
                'lastActivity'=> $session->getLastActivity()->format('d/m/Y H:i'),
                'isActive'    => $session->isActive(),
            ];
        }, $sessions);

        return new JsonResponse($data);
    }

    /**
     * Revoke (deactivate) a specific session.
     * If the revoked session is the current one, fully log the user out.
     */
    #[Route('/sessions/{id}/revoke', name: 'app_user_session_revoke', methods: ['POST'])]
    public function revokeSession(
        string $id,
        Request $request,
        UserSessionService $sessionService
    ): Response {
        $user = $this->getUser();

        // Verify the session belongs to this user (security check)
        $sessions = $sessionService->getActiveSessions($user);
        $sessionBelongsToUser = false;
        foreach ($sessions as $session) {
            if ($session->getId() === $id) {
                $sessionBelongsToUser = true;
                break;
            }
        }

        if (!$sessionBelongsToUser) {
            $this->addFlash('error', 'Session introuvable ou accès refusé.');
            return $this->redirectToRoute('app_user_profile');
        }

        // Deactivate the session in DB
        $sessionService->deactivateSession($id);

        // Check if this is the CURRENT session
        $currentSessionId = $request->getSession()->get('current_user_session_id');
        if ($currentSessionId === $id) {
            // This is the user's own current session — force full logout
            $request->getSession()->invalidate();
            $this->container->get('security.token_storage')->setToken(null);
            return $this->redirectToRoute('app_login');
        }

        // Otherwise it's a remote session — stay on profile
        $this->addFlash('success', 'L\'appareil a été déconnecté avec succès.');

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['status' => 'ok']);
        }

        return $this->redirectToRoute('app_user_profile');
    }

    /**
     * Revoke all sessions — logs the user out since their current session is included.
     */
    #[Route('/sessions/revoke-all', name: 'app_user_sessions_revoke_all', methods: ['POST'])]
    public function revokeAllSessions(
        Request $request,
        UserSessionService $sessionService
    ): Response {
        $user = $this->getUser();

        // Deactivate ALL sessions in DB
        $sessionService->deactivateAllSessions($user);

        // Force full logout since current session is also revoked
        $request->getSession()->invalidate();
        $this->container->get('security.token_storage')->setToken(null);

        return $this->redirectToRoute('app_login');
    }

    /**
     * Enroll / update face encoding via AJAX.
     * Used from the profile page to set up or re-capture Face ID.
     */
    #[Route('/enroll-face', name: 'app_user_enroll_face', methods: ['POST'])]
    public function enrollFace(
        Request $request,
        FaceRecognitionService $faceRecognitionService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ): JsonResponse {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $base64Image = $data['image'] ?? '';

        if (empty($base64Image)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucune image reçue.',
            ], 400);
        }

        $result = $faceRecognitionService->encodeFace($base64Image);

        if ($result['success'] && !empty($result['encoding'])) {
            $user->setFaceEncoding($result['encoding']);
            $entityManager->flush();

            $logger->info('[Profile] Face encoding updated', [
                'user' => $user->getEmail(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Face ID configuré avec succès !',
            ]);
        }

        return new JsonResponse([
            'success' => false,
            'message' => $result['message'] ?? 'Impossible d\'encoder le visage.',
        ]);
    }

    /**
     * Remove face encoding via AJAX.
     * Deletes the stored face data from the user's profile.
     */
    #[Route('/remove-face', name: 'app_user_remove_face', methods: ['POST'])]
    public function removeFace(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ): JsonResponse {
        $user = $this->getUser();

        $user->setFaceEncoding(null);
        $entityManager->flush();

        $logger->info('[Profile] Face encoding removed', [
            'user' => $user->getEmail(),
        ]);

        return new JsonResponse([
            'success' => true,
            'message' => 'Face ID supprimé avec succès.',
        ]);
    }
}

