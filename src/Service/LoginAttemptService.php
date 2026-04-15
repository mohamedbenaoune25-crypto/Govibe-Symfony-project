<?php

namespace App\Service;

use App\Entity\LoginAttempt;
use App\Entity\Personne;
use App\Repository\LoginAttemptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Audit logging service for all login attempts.
 * Also provides brute-force detection helpers.
 */
class LoginAttemptService
{
    private const LOCKOUT_THRESHOLD = 5;       // max failed attempts
    private const LOCKOUT_WINDOW_MINUTES = 30; // within this window
    private const LOCKOUT_DURATION_MINUTES = 60; // lockout duration

    private EntityManagerInterface $entityManager;
    private LoginAttemptRepository $loginAttemptRepo;
    private DeviceDetectorService $deviceDetector;
    private GeoIPService $geoIPService;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoginAttemptRepository $loginAttemptRepo,
        DeviceDetectorService $deviceDetector,
        GeoIPService $geoIPService,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->loginAttemptRepo = $loginAttemptRepo;
        $this->deviceDetector = $deviceDetector;
        $this->geoIPService = $geoIPService;
        $this->logger = $logger;
    }

    /**
     * Record a login attempt (successful or failed) in the audit log.
     */
    public function recordAttempt(
        Personne $user,
        Request $request,
        bool $success,
        float $riskScore = 0.0,
        string $authLevel = 'LOW'
    ): LoginAttempt {
        $ip = $request->getClientIp() ?? '127.0.0.1';
        $ua = $request->headers->get('User-Agent', '');
        $device = $this->deviceDetector->detect($ua);
        $geo = $this->geoIPService->locate($ip);

        $attempt = new LoginAttempt();
        $attempt->setUser($user);
        $attempt->setIpAddress($ip);
        $attempt->setDevice($device);
        $attempt->setCountry($geo['country']);
        $attempt->setSuccess($success);
        $attempt->setRiskScore($riskScore);
        $attempt->setAuthLevel($authLevel);

        $this->entityManager->persist($attempt);
        $this->entityManager->flush();

        $this->logger->info('[LoginAudit] Attempt recorded', [
            'user'      => $user->getEmail(),
            'success'   => $success,
            'ip'        => $ip,
            'device'    => $device,
            'country'   => $geo['country'],
            'riskScore' => $riskScore,
            'authLevel' => $authLevel,
        ]);

        return $attempt;
    }

    /**
     * Count recent failed attempts within the lockout window.
     */
    public function countRecentFailedAttempts(Personne $user): int
    {
        $since = (new \DateTime())->modify('-' . self::LOCKOUT_WINDOW_MINUTES . ' minutes');
        return $this->loginAttemptRepo->countRecentFailed($user, $since);
    }

    /**
     * Check if the user should be locked out based on failed attempts.
     * If threshold is reached, lock the account.
     *
     * @return bool true if account was just locked
     */
    public function checkAndLockIfNeeded(Personne $user): bool
    {
        $failedCount = $this->countRecentFailedAttempts($user);

        if ($failedCount >= self::LOCKOUT_THRESHOLD) {
            $lockoutUntil = (new \DateTime())->modify('+' . self::LOCKOUT_DURATION_MINUTES . ' minutes');
            $user->setLockoutUntil($lockoutUntil);
            $user->setIsAccountLocked(true);
            $this->entityManager->flush();

            $this->logger->warning('[LoginAudit] Account locked due to brute-force', [
                'user' => $user->getEmail(),
                'failedCount' => $failedCount,
                'lockedUntil' => $lockoutUntil->format('Y-m-d H:i:s'),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Check if the user's account is currently locked.
     *
     * @return array{locked: bool, remaining_minutes: int}
     */
    public function isAccountLocked(Personne $user): array
    {
        $lockoutUntil = $user->getLockoutUntil();

        if ($lockoutUntil === null) {
            return ['locked' => false, 'remaining_minutes' => 0];
        }

        $now = new \DateTime();
        if ($lockoutUntil > $now) {
            $diff = $now->diff($lockoutUntil);
            $remainingMinutes = ($diff->h * 60) + $diff->i + 1; // +1 to round up

            return ['locked' => true, 'remaining_minutes' => $remainingMinutes];
        }

        // Lockout expired — unlock the account
        $user->setLockoutUntil(null);
        $user->setIsAccountLocked(false);
        $this->entityManager->flush();

        return ['locked' => false, 'remaining_minutes' => 0];
    }

    /**
     * Get the last successful login attempt for context comparison.
     */
    public function getLastSuccessfulAttempt(Personne $user): ?LoginAttempt
    {
        return $this->loginAttemptRepo->findLastSuccessful($user);
    }
}
