<?php

namespace App\Controller\Web;

use App\Entity\Personne;
use App\Repository\PersonneRepository;
use App\Service\FaceRecognitionService;
use App\Service\LoginAttemptService;
use App\Service\OTPService;
use App\Service\RiskScoringService;
use App\Service\UserSessionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class SecurityController extends AbstractController
{
    #[Route(path: '/', name: 'app_home')]
    public function home(): Response
    {
        if ($this->getUser()) {
            if (in_array('ROLE_ADMIN', $this->getUser()->getRoles(), true)) {
                return $this->redirectToRoute('app_admin_dashboard');
            }
            return $this->redirectToRoute('app_user_home');
        }

        return $this->redirectToRoute('app_login');
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(
        AuthenticationUtils $authenticationUtils,
        FaceRecognitionService $faceRecognitionService
    ): Response {
        if ($this->getUser()) {
            if (in_array('ROLE_ADMIN', $this->getUser()->getRoles(), true)) {
                return $this->redirectToRoute('app_admin_dashboard');
            }
            return $this->redirectToRoute('app_user_home');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        // Filter out the MFA redirect sentinel from displayed errors
        if ($error && $error->getMessageKey() === '__MFA_REQUIRED__') {
            $error = null;
        }

        // Check if face service is available for Face ID login tab
        $faceServiceAvailable = $faceRecognitionService->isServiceAvailable();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'face_service_available' => $faceServiceAvailable,
        ]);
    }

    /**
     * Face ID Login — Authenticate with email + face (no password).
     *
     * Flow:
     *   1. Receive email + base64 face image via AJAX
     *   2. Find user by email
     *   3. Check stored face encoding exists
     *   4. Verify face via Python API
     *   5. Evaluate risk (same as password login)
     *   6. LOW risk → direct login
     *   7. HIGH/VERY_HIGH → redirect to MFA (OTP or Face re-verify)
     */
    #[Route(path: '/login/face', name: 'app_login_face', methods: ['POST'])]
    public function loginWithFace(
        Request $request,
        PersonneRepository $personneRepo,
        FaceRecognitionService $faceRecognitionService,
        RiskScoringService $riskScoring,
        LoginAttemptService $loginAttemptService,
        UserSessionService $sessionService,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';
        $base64Image = $data['image'] ?? '';

        // Validate input
        if (empty($email) || empty($base64Image)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Email et image du visage requis.',
            ], 400);
        }

        // Find user
        $user = $personneRepo->findOneBy(['email' => $email]);
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucun compte trouvé avec cet email.',
            ]);
        }

        // Check account lockout
        $lockoutInfo = $loginAttemptService->isAccountLocked($user);
        if ($lockoutInfo['locked']) {
            return new JsonResponse([
                'success' => false,
                'message' => sprintf(
                    'Compte verrouillé. Réessayez dans %d minute(s).',
                    $lockoutInfo['remaining_minutes']
                ),
            ]);
        }

        // Check face encoding exists
        $storedEncoding = $user->getFaceEncoding();
        if (empty($storedEncoding)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Face ID non configuré pour ce compte. Utilisez le mot de passe.',
            ]);
        }

        // Verify face via Python API
        $result = $faceRecognitionService->verifyFace($base64Image, $storedEncoding);

        if (!$result['success']) {
            return new JsonResponse([
                'success' => false,
                'message' => $result['message'],
            ]);
        }

        if (!$result['match']) {
            // Record failed attempt
            $loginAttemptService->recordAttempt($user, $request, false, 0, 'FACE_FAILED');
            $loginAttemptService->checkAndLockIfNeeded($user);

            $logger->warning('[FaceLogin] Face verification failed', [
                'user' => $email,
                'distance' => $result['distance'],
                'confidence' => $result['confidence'],
            ]);

            return new JsonResponse([
                'success' => false,
                'message' => sprintf(
                    'Visage non reconnu (confiance: %d%%). Réessayez ou utilisez votre mot de passe.',
                    $result['confidence']
                ),
            ]);
        }

        // Face matched! Now evaluate risk
        $logger->info('[FaceLogin] Face verified', [
            'user' => $email,
            'distance' => $result['distance'],
            'confidence' => $result['confidence'],
        ]);

        $riskResult = $riskScoring->evaluateRisk($user, $request);
        $authLevel = $riskResult['auth_level'];
        $riskProbability = $riskResult['risk_probability'];

        if ($authLevel === 'LOW') {
            // Direct login
            $loginAttemptService->recordAttempt($user, $request, true, $riskProbability, $authLevel);
            $sessionService->createSession($user, $request);

            // Manually authenticate
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $tokenStorage->setToken($token);
            $request->getSession()->set('_security_main', serialize($token));

            $redirectUrl = in_array('ROLE_ADMIN', $user->getRoles(), true)
                ? $this->generateUrl('app_admin_dashboard')
                : $this->generateUrl('app_user_home');

            return new JsonResponse([
                'success' => true,
                'message' => 'Authentification réussie !',
                'redirect' => $redirectUrl,
            ]);
        }

        // HIGH or VERY_HIGH — Need MFA
        $session = $request->getSession();
        $this->setupMfaSession($session, $user, $authLevel, $riskProbability, $riskResult['source']);

        // Generate and send OTP as backup
        // (user will choose between OTP and Face on the MFA page)

        return new JsonResponse([
            'success' => true,
            'mfa_required' => true,
            'message' => 'Vérification supplémentaire requise.',
            'redirect' => $this->generateUrl('app_mfa_verify'),
        ]);
    }

    /**
     * MFA Verification Page — displays OTP form + Face ID option.
     */
    #[Route(path: '/login/verify-mfa', name: 'app_mfa_verify', methods: ['GET', 'POST'])]
    public function verifyMfa(
        Request $request,
        PersonneRepository $personneRepo,
        OTPService $otpService,
        LoginAttemptService $loginAttemptService,
        UserSessionService $sessionService,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger
    ): Response {
        $session = $request->getSession();
        $userId = $session->get('mfa_pending_user_id');
        $email = $session->get('mfa_pending_email');

        // No pending MFA — redirect to login
        if (!$userId || !$email) {
            return $this->redirectToRoute('app_login');
        }

        $user = $personneRepo->find($userId);
        if (!$user) {
            $session->remove('mfa_pending_user_id');
            return $this->redirectToRoute('app_login');
        }

        $authLevel = $session->get('mfa_auth_level', 'HIGH');
        $riskProbability = $session->get('mfa_risk_probability', 0);
        $otpSentAt = $session->get('mfa_otp_sent_at', 0);
        $errorMessage = null;

        // Send OTP if not yet sent
        if ($otpSentAt === 0) {
            $otpService->generateAndSendOTP($user);
            $session->set('mfa_otp_sent_at', time());
            $otpSentAt = time();
        }

        // Handle POST — OTP submission
        if ($request->isMethod('POST')) {
            $submittedCode = $request->request->get('otp_code', '');

            // Combine individual digit fields if needed
            if (empty($submittedCode)) {
                $digits = [];
                for ($i = 1; $i <= 6; $i++) {
                    $digits[] = $request->request->get('digit_' . $i, '');
                }
                $submittedCode = implode('', $digits);
            }

            $logger->info('[MFA] OTP submitted', [
                'user' => $email,
                'codeLength' => strlen($submittedCode),
            ]);

            if ($otpService->validateOTP($user, $submittedCode)) {
                // OTP is valid — complete authentication
                $this->completeMfaAuthentication(
                    $user, $request, $session,
                    $loginAttemptService, $sessionService, $tokenStorage,
                    $riskProbability, $authLevel, $logger
                );

                return $this->redirectAfterLogin($user);
            } else {
                $errorMessage = 'Code invalide ou expiré. Veuillez réessayer.';
                $loginAttemptService->recordAttempt(
                    $user, $request, false, $riskProbability, 'MFA_FAILED'
                );
            }
        }

        // Calculate remaining time for OTP
        $expiresIn = max(0, ($otpSentAt + 300) - time()); // 300 seconds = 5 minutes

        // Check if user has face enrolled (for Face tab)
        $hasFaceEnrolled = !empty($user->getFaceEncoding());

        return $this->render('security/mfa_verify.html.twig', [
            'email' => $this->maskEmail($email),
            'auth_level' => $authLevel,
            'expires_in' => $expiresIn,
            'error' => $errorMessage,
            'has_face_enrolled' => $hasFaceEnrolled,
        ]);
    }

    /**
     * MFA Face Verification — AJAX endpoint for face-based MFA.
     * User chooses Face ID instead of OTP during MFA challenge.
     */
    #[Route(path: '/login/verify-face-mfa', name: 'app_mfa_verify_face', methods: ['POST'])]
    public function verifyFaceMfa(
        Request $request,
        PersonneRepository $personneRepo,
        FaceRecognitionService $faceRecognitionService,
        LoginAttemptService $loginAttemptService,
        UserSessionService $sessionService,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger
    ): JsonResponse {
        $session = $request->getSession();
        $userId = $session->get('mfa_pending_user_id');

        if (!$userId) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Session MFA expirée. Veuillez vous reconnecter.',
            ], 401);
        }

        $user = $personneRepo->find($userId);
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        // Check face encoding exists
        $storedEncoding = $user->getFaceEncoding();
        if (empty($storedEncoding)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Face ID non configuré. Utilisez le code OTP.',
            ]);
        }

        $data = json_decode($request->getContent(), true);
        $base64Image = $data['image'] ?? '';

        if (empty($base64Image)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucune image reçue.',
            ], 400);
        }

        // Verify face
        $result = $faceRecognitionService->verifyFace($base64Image, $storedEncoding);

        if (!$result['success']) {
            return new JsonResponse([
                'success' => false,
                'message' => $result['message'],
            ]);
        }

        $authLevel = $session->get('mfa_auth_level', 'HIGH');
        $riskProbability = $session->get('mfa_risk_probability', 0);

        if (!$result['match']) {
            $loginAttemptService->recordAttempt(
                $user, $request, false, $riskProbability, 'MFA_FACE_FAILED'
            );

            $logger->warning('[MFA-Face] Verification failed', [
                'user' => $user->getEmail(),
                'distance' => $result['distance'],
            ]);

            return new JsonResponse([
                'success' => false,
                'match' => false,
                'confidence' => $result['confidence'],
                'message' => sprintf(
                    'Visage non reconnu (confiance: %d%%). Réessayez ou utilisez le code OTP.',
                    $result['confidence']
                ),
            ]);
        }

        // Face matched — complete authentication
        $logger->info('[MFA-Face] Face verified successfully', [
            'user' => $user->getEmail(),
            'confidence' => $result['confidence'],
        ]);

        $this->completeMfaAuthentication(
            $user, $request, $session,
            $loginAttemptService, $sessionService, $tokenStorage,
            $riskProbability, $authLevel, $logger
        );

        $redirectUrl = in_array('ROLE_ADMIN', $user->getRoles(), true)
            ? $this->generateUrl('app_admin_dashboard')
            : $this->generateUrl('app_user_home');

        return new JsonResponse([
            'success' => true,
            'match' => true,
            'confidence' => $result['confidence'],
            'message' => 'Vérification réussie !',
            'redirect' => $redirectUrl,
        ]);
    }

    /**
     * Resend OTP code.
     */
    #[Route(path: '/login/resend-otp', name: 'app_mfa_resend', methods: ['POST'])]
    public function resendOtp(
        Request $request,
        PersonneRepository $personneRepo,
        OTPService $otpService,
        LoggerInterface $logger
    ): Response {
        $session = $request->getSession();
        $userId = $session->get('mfa_pending_user_id');

        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $user = $personneRepo->find($userId);
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Rate limit: don't resend within 30 seconds
        $lastSentAt = $session->get('mfa_otp_sent_at', 0);
        if ((time() - $lastSentAt) < 30) {
            $this->addFlash('error', 'Veuillez attendre 30 secondes avant de renvoyer le code.');
            return $this->redirectToRoute('app_mfa_verify');
        }

        $otpService->generateAndSendOTP($user);
        $session->set('mfa_otp_sent_at', time());

        $logger->info('[MFA] OTP resent', ['user' => $user->getEmail()]);
        $this->addFlash('success', 'Un nouveau code a été envoyé à votre adresse email.');

        return $this->redirectToRoute('app_mfa_verify');
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    // ─── Private Helper Methods ───────────────────────────────────────

    /**
     * Set up MFA session variables when risk is HIGH or VERY_HIGH.
     */
    private function setupMfaSession(
        $session,
        Personne $user,
        string $authLevel,
        float $riskProbability,
        string $riskSource
    ): void {
        $session->set('mfa_pending_user_id', $user->getId());
        $session->set('mfa_pending_email', $user->getEmail());
        $session->set('mfa_auth_level', $authLevel);
        $session->set('mfa_risk_probability', $riskProbability);
        $session->set('mfa_risk_source', $riskSource);
        $session->set('mfa_otp_sent_at', 0); // Will be sent on MFA page load
    }

    /**
     * Complete MFA authentication — authenticate user, create session, clean up.
     */
    private function completeMfaAuthentication(
        Personne $user,
        Request $request,
        $session,
        LoginAttemptService $loginAttemptService,
        UserSessionService $sessionService,
        TokenStorageInterface $tokenStorage,
        float $riskProbability,
        string $authLevel,
        LoggerInterface $logger
    ): void {
        $logger->info('[MFA] Authentication completed', ['user' => $user->getEmail()]);

        // Record successful login
        $loginAttemptService->recordAttempt($user, $request, true, $riskProbability, $authLevel);

        // Create user session
        $sessionService->createSession($user, $request);

        // Manually authenticate the user
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->setToken($token);
        $session->set('_security_main', serialize($token));

        // Clear MFA session data
        $session->remove('mfa_pending_user_id');
        $session->remove('mfa_pending_email');
        $session->remove('mfa_auth_level');
        $session->remove('mfa_risk_probability');
        $session->remove('mfa_risk_source');
        $session->remove('mfa_otp_sent_at');

        $this->addFlash('success', 'Vérification réussie. Bienvenue !');
    }

    /**
     * Redirect user after successful login based on role.
     */
    private function redirectAfterLogin(Personne $user): Response
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('app_admin_dashboard');
        }
        return $this->redirectToRoute('app_user_home');
    }

    /**
     * Mask email for display on MFA page (e.g., az***@gmail.com).
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        if (strlen($local) <= 2) {
            $masked = $local[0] . '***';
        } else {
            $masked = substr($local, 0, 2) . str_repeat('*', max(3, strlen($local) - 2));
        }

        return $masked . '@' . $domain;
    }
}
