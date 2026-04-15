<?php

namespace App\Service;

use App\Entity\OtpCode;
use App\Entity\Personne;
use App\Repository\OtpCodeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

/**
 * OTP (One-Time Password) generation, delivery, and validation service.
 */
class OTPService
{
    private const OTP_LENGTH = 6;
    private const OTP_VALIDITY_MINUTES = 5;

    private EntityManagerInterface $entityManager;
    private OtpCodeRepository $otpCodeRepo;
    private MailerInterface $mailer;
    private Environment $twig;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        OtpCodeRepository $otpCodeRepo,
        MailerInterface $mailer,
        Environment $twig,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->otpCodeRepo = $otpCodeRepo;
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
    }

    /**
     * Generate a new OTP, store it, and send it via email.
     */
    public function generateAndSendOTP(Personne $user): OtpCode
    {
        // Invalidate any existing unused OTPs for this user
        $this->otpCodeRepo->invalidateAllForUser($user);

        // Generate a random 6-digit code
        $plainCode = $this->generateCode();

        // Create and persist the OTP entity
        $otp = new OtpCode();
        $otp->setCode($plainCode);
        $otp->setExpiresAt(
            (new \DateTime())->modify('+' . self::OTP_VALIDITY_MINUTES . ' minutes')
        );
        $otp->setUser($user);
        $otp->setUsed(false);

        $this->entityManager->persist($otp);
        $this->entityManager->flush();

        // Send the OTP via email
        $this->sendOTPEmail($user, $plainCode);

        $this->logger->info('[OTP] Code generated and sent', [
            'user' => $user->getEmail(),
            'expires_in' => self::OTP_VALIDITY_MINUTES . ' minutes',
        ]);

        return $otp;
    }

    /**
     * Validate an OTP code submitted by the user.
     */
    public function validateOTP(Personne $user, string $submittedCode): bool
    {
        $otp = $this->otpCodeRepo->findLatestValidOtp($user);

        if ($otp === null) {
            $this->logger->warning('[OTP] No valid OTP found', ['user' => $user->getEmail()]);
            return false;
        }

        // Check if the code matches
        if ($otp->getCode() !== $submittedCode) {
            $this->logger->warning('[OTP] Invalid code submitted', ['user' => $user->getEmail()]);
            return false;
        }

        // Check expiry
        if ($otp->getExpiresAt() < new \DateTime()) {
            $this->logger->warning('[OTP] Code has expired', ['user' => $user->getEmail()]);
            return false;
        }

        // Mark as used
        $otp->setUsed(true);
        $this->entityManager->flush();

        $this->logger->info('[OTP] Code validated successfully', ['user' => $user->getEmail()]);
        return true;
    }

    /**
     * Generate a random numeric code of the specified length.
     */
    private function generateCode(): string
    {
        $min = (int) pow(10, self::OTP_LENGTH - 1);
        $max = (int) pow(10, self::OTP_LENGTH) - 1;

        return (string) random_int($min, $max);
    }

    /**
     * Send the OTP code by email using a premium template.
     */
    private function sendOTPEmail(Personne $user, string $code): void
    {
        try {
            $htmlBody = $this->twig->render('emails/otp_email.html.twig', [
                'user' => $user,
                'code' => $code,
                'validity_minutes' => self::OTP_VALIDITY_MINUTES,
            ]);

            $email = (new Email())
                ->from('noreply@govibe.com')
                ->to($user->getEmail())
                ->subject('🔐 GoVibe — Votre code de vérification')
                ->html($htmlBody);

            $this->mailer->send($email);

            $this->logger->info('[OTP] Email sent successfully', ['to' => $user->getEmail()]);

        } catch (\Throwable $e) {
            $this->logger->error('[OTP] Failed to send email', [
                'to' => $user->getEmail(),
                'error' => $e->getMessage(),
            ]);
            // Don't throw — the OTP is still in DB, user can request resend
        }
    }
}
