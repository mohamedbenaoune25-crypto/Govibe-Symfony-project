<?php

namespace App\Controller;

use App\Entity\Personne;
use App\Form\RegistrationFormType;
use App\Service\FaceRecognitionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        FaceRecognitionService $faceRecognitionService,
        LoggerInterface $logger
    ): Response {
        $user = new Personne();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // Encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            // Handle face encoding from hidden field
            $faceDataJson = $request->request->get('face_encoding_data', '');
            $logger->info('[Registration] Face data field value length: ' . strlen($faceDataJson));

            if (!empty($faceDataJson)) {
                $faceData = json_decode($faceDataJson, true);
                if (is_array($faceData) && count($faceData) >= 64) {
                    $user->setFaceEncoding($faceData);
                    $logger->info('[Registration] Face encoding stored for new user', [
                        'email' => $user->getEmail(),
                        'dimensions' => count($faceData),
                    ]);
                } else {
                    $logger->warning('[Registration] Invalid face encoding data received', [
                        'type' => gettype($faceData),
                        'count' => is_array($faceData) ? count($faceData) : 'N/A',
                        'raw_preview' => substr($faceDataJson, 0, 200),
                    ]);
                }
            } else {
                $logger->info('[Registration] No face encoding data submitted');
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        // Check if face service is available
        $faceServiceAvailable = $faceRecognitionService->isServiceAvailable();

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
            'face_service_available' => $faceServiceAvailable,
        ]);
    }

    /**
     * AJAX endpoint — Encode a face image during registration.
     * Called by the frontend webcam capture to get the face encoding.
     */
    #[Route('/register/encode-face', name: 'app_register_encode_face', methods: ['POST'])]
    public function encodeFace(
        Request $request,
        FaceRecognitionService $faceRecognitionService,
        LoggerInterface $logger
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $base64Image = $data['image'] ?? '';

        if (empty($base64Image)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucune image reçue.',
            ], 400);
        }

        $logger->info('[Registration] Face encoding request received');

        $result = $faceRecognitionService->encodeFace($base64Image);

        return new JsonResponse($result);
    }
}
