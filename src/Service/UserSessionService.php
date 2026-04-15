<?php

namespace App\Service;

use App\Entity\Personne;
use App\Entity\UserSession;
use App\Repository\UserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * Manages user sessions: creation, listing, revocation, and new-location alerts.
 */
class UserSessionService
{
    private EntityManagerInterface $entityManager;
    private UserSessionRepository $sessionRepo;
    private DeviceDetectorService $deviceDetector;
    private GeoIPService $geoIPService;
    private MailerInterface $mailer;
    private Environment $twig;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserSessionRepository $sessionRepo,
        DeviceDetectorService $deviceDetector,
        GeoIPService $geoIPService,
        MailerInterface $mailer,
        Environment $twig,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->sessionRepo = $sessionRepo;
        $this->deviceDetector = $deviceDetector;
        $this->geoIPService = $geoIPService;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
    }

    /**
     * Create a new session record after successful login.
     */
    public function createSession(Personne $user, Request $request): UserSession
    {
        $ip = $request->getClientIp() ?? '127.0.0.1';
        $ua = $request->headers->get('User-Agent', '');
        $device = $this->deviceDetector->detect($ua);
        $geo = $this->geoIPService->locate($ip);

        // Deduplication check: Avoid double creation by event subscribers
        $recentSession = $this->sessionRepo->createQueryBuilder('us')
            ->where('us.user = :user')
            ->andWhere('us.ipAddress = :ip')
            ->andWhere('us.deviceName = :device')
            ->andWhere('us.loginDate > :recent')
            ->setParameter('user', $user)
            ->setParameter('ip', $ip)
            ->setParameter('device', $device)
            ->setParameter('recent', (new \DateTime())->modify('-30 seconds'))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($recentSession !== null) {
            $this->logger->info('[Session] Duplicate session creation prevented', [
                'user' => $user->getEmail()
            ]);
            return $recentSession;
        }

        $session = new UserSession();
        $session->setId($this->generateUUID());
        $session->setUser($user);
        $session->setIpAddress($ip);
        $session->setDeviceName($device);
        $session->setCountry($geo['country']);
        $session->setCity($geo['city']);
        $session->setIsActive(true);

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        // Store this session ID in the HTTP session for accurate logout tracking
        $request->getSession()->set('current_user_session_id', $session->getId());

        // Check for new location and send alert if needed
        $this->checkNewLocation($user, $geo['country'], $geo['city'], $device, $ip);

        $this->logger->info('[Session] New session created', [
            'user' => $user->getEmail(),
            'sessionId' => $session->getId(),
            'device' => $device,
            'country' => $geo['country'],
        ]);

        return $session;
    }

    /**
     * Get all active sessions for a user.
     *
     * @return UserSession[]
     */
    public function getActiveSessions(Personne $user): array
    {
        return $this->sessionRepo->findActiveSessions($user);
    }

    /**
     * Deactivate a specific session (remote logout).
     */
    public function deactivateSession(string $sessionId): bool
    {
        $session = $this->sessionRepo->find($sessionId);

        if ($session === null) {
            return false;
        }

        $session->setIsActive(false);
        $this->entityManager->flush();

        $this->logger->info('[Session] Session deactivated', [
            'sessionId' => $sessionId,
            'user' => $session->getUser()->getEmail(),
        ]);

        return true;
    }

    /**
     * Deactivate all sessions for a user, optionally except one.
     */
    public function deactivateAllSessions(Personne $user, ?string $exceptSessionId = null): void
    {
        $this->sessionRepo->deactivateAllExcept($user, $exceptSessionId);

        $this->logger->info('[Session] All sessions deactivated', [
            'user' => $user->getEmail(),
            'except' => $exceptSessionId,
        ]);
    }

    /**
     * Check if login is from a new location and send alert email.
     */
    private function checkNewLocation(Personne $user, string $country, string $city, string $device, string $ip): void
    {
        // Skip alert for local/private IPs
        if ($country === 'Local') {
            return;
        }

        $knownCountries = $this->sessionRepo->findDistinctCountries($user);

        // If user has no previous sessions, no alert needed (first login)
        if (empty($knownCountries)) {
            return;
        }

        if (!in_array($country, $knownCountries, true)) {
            $this->sendNewLocationAlert($user, $country, $city, $device, $ip);
        }
    }

    /**
     * Send email alert for new location login.
     */
    private function sendNewLocationAlert(Personne $user, string $country, string $city, string $device, string $ip): void
    {
        try {
            $htmlBody = $this->twig->render('emails/new_login_alert.html.twig', [
                'user'    => $user,
                'country' => $country,
                'city'    => $city,
                'device'  => $device,
                'ip'      => $ip,
                'date'    => new \DateTime(),
            ]);

            $email = (new Email())
                ->from('noreply@govibe.com')
                ->to($user->getEmail())
                ->subject('⚠️ GoVibe — Nouvelle connexion détectée')
                ->html($htmlBody);

            $this->mailer->send($email);

            $this->logger->info('[Session] New location alert sent', [
                'user' => $user->getEmail(),
                'country' => $country,
                'city' => $city,
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('[Session] Failed to send new location alert', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a UUID v4.
     */
    private function generateUUID(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
