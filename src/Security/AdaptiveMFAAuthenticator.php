<?php

namespace App\Security;

use App\Entity\Personne;
use App\Repository\PersonneRepository;
use App\Service\LoginAttemptService;
use App\Service\OTPService;
use App\Service\RiskScoringService;
use App\Service\UserSessionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Adaptive MFA Authenticator — Orchestrates the entire login flow:
 *
 * 1. Verify email + password
 * 2. Check account lockout
 * 3. Check brute-force threshold
 * 4. Call AI Risk Scoring API
 * 5. Determine auth level (LOW / HIGH / VERY_HIGH)
 * 6. LOW → direct login
 * 7. HIGH/VERY_HIGH → generate OTP, redirect to MFA verification
 */
class AdaptiveMFAAuthenticator extends AbstractLoginFormAuthenticator
{
    private PersonneRepository $personneRepo;
    private UserPasswordHasherInterface $passwordHasher;
    private RiskScoringService $riskScoring;
    private LoginAttemptService $loginAttemptService;
    private OTPService $otpService;
    private UserSessionService $sessionService;
    private UrlGeneratorInterface $urlGenerator;
    private LoggerInterface $logger;

    public function __construct(
        PersonneRepository $personneRepo,
        UserPasswordHasherInterface $passwordHasher,
        RiskScoringService $riskScoring,
        LoginAttemptService $loginAttemptService,
        OTPService $otpService,
        UserSessionService $sessionService,
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger
    ) {
        $this->personneRepo = $personneRepo;
        $this->passwordHasher = $passwordHasher;
        $this->riskScoring = $riskScoring;
        $this->loginAttemptService = $loginAttemptService;
        $this->otpService = $otpService;
        $this->sessionService = $sessionService;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('app_login');
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('_username', '');
        $password = $request->request->get('_password', '');
        $csrfToken = $request->request->get('_csrf_token', '');

        // Store last username in session for the login form
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        // === Step 1: Find user ===
        $user = $this->personneRepo->findOneBy(['email' => $email]);

        if (!$user) {
            throw new CustomUserMessageAuthenticationException('Identifiants invalides.');
        }

        // === Step 2: Check account lockout ===
        $lockoutInfo = $this->loginAttemptService->isAccountLocked($user);
        if ($lockoutInfo['locked']) {
            throw new CustomUserMessageAuthenticationException(
                sprintf('Compte verrouillé. Réessayez dans %d minute(s).', $lockoutInfo['remaining_minutes'])
            );
        }

        // === Step 3: Verify password ===
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            // Record failed attempt
            $this->loginAttemptService->recordAttempt($user, $request, false, 0, 'FAILED');

            // Check if we should lock the account
            $wasLocked = $this->loginAttemptService->checkAndLockIfNeeded($user);
            if ($wasLocked) {
                throw new CustomUserMessageAuthenticationException(
                    'Trop de tentatives échouées. Votre compte est verrouillé pour 60 minutes.'
                );
            }

            throw new CustomUserMessageAuthenticationException('Identifiants invalides.');
        }

        // === Step 4: Password is correct — Evaluate risk ===
        $riskResult = $this->riskScoring->evaluateRisk($user, $request);
        $authLevel = $riskResult['auth_level'];
        $riskProbability = $riskResult['risk_probability'];

        $this->logger->info('[MFA Authenticator] Risk evaluated', [
            'user' => $email,
            'authLevel' => $authLevel,
            'riskProbability' => $riskProbability,
            'source' => $riskResult['source'],
        ]);

        // === Step 5: Decision ===
        if ($authLevel === 'LOW') {
            // Direct login — record success
            $this->loginAttemptService->recordAttempt($user, $request, true, $riskProbability, $authLevel);

            // Create session
            $this->sessionService->createSession($user, $request);

        } else {
            // HIGH or VERY_HIGH — Need MFA verification
            // Generate and send OTP
            $this->otpService->generateAndSendOTP($user);

            // Store pending MFA state in session
            $session = $request->getSession();
            $session->set('mfa_pending_user_id', $user->getId());
            $session->set('mfa_pending_email', $user->getEmail());
            $session->set('mfa_auth_level', $authLevel);
            $session->set('mfa_risk_probability', $riskProbability);
            $session->set('mfa_risk_source', $riskResult['source']);
            $session->set('mfa_otp_sent_at', time());

            // Record as pending (not yet successful)
            $this->loginAttemptService->recordAttempt($user, $request, false, $riskProbability, 'MFA_PENDING');

            // Throw a special exception to redirect to MFA page
            // We use a session flag instead so the controller can handle it
            throw new CustomUserMessageAuthenticationException('__MFA_REQUIRED__');
        }

        // Return a valid passport for LOW risk
        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // The LoginSuccessSubscriber handles the redirect
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $message = $exception->getMessageKey();

        // Check if this is an MFA redirect
        if ($message === '__MFA_REQUIRED__') {
            // Redirect to MFA verification page
            return new RedirectResponse($this->urlGenerator->generate('app_mfa_verify'));
        }

        // Store the error in session for the login form to display
        $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

        return new RedirectResponse($this->getLoginUrl($request));
    }
}
