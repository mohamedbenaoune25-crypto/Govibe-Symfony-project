<?php

namespace App\EventSubscriber;

use App\Service\DeviceDetectorService;
use App\Service\UserSessionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Psr\Log\LoggerInterface;
use App\Entity\Personne;
use App\Repository\UserSessionRepository;

class LogoutSubscriber implements EventSubscriberInterface
{
    private UserSessionRepository $sessionRepo;
    private EntityManagerInterface $entityManager;
    private DeviceDetectorService $deviceDetector;
    private LoggerInterface $logger;

    public function __construct(
        UserSessionRepository $sessionRepo,
        EntityManagerInterface $entityManager,
        DeviceDetectorService $deviceDetector,
        LoggerInterface $logger
    ) {
        $this->sessionRepo = $sessionRepo;
        $this->entityManager = $entityManager;
        $this->deviceDetector = $deviceDetector;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof Personne) {
            return;
        }

        $request = $event->getRequest();
        $sessionId = $request->getSession()->get('current_user_session_id');

        if ($sessionId) {
            $session = $this->sessionRepo->find($sessionId);
        } else {
            // Fallback if not in HTTP session for some reason
            $session = $this->sessionRepo->createQueryBuilder('us')
                ->where('us.user = :user')
                ->andWhere('us.isActive = true')
                ->setParameter('user', $user)
                ->orderBy('us.lastActivity', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }

        if ($session !== null) {
            $session->setIsActive(false);
            $session->setLastActivity(new \DateTime());
            $this->entityManager->flush();

            $request->getSession()->remove('current_user_session_id');

            $this->logger->info('[LogoutSubscriber] Session marked as inactive on logout', [
                'user' => $user->getEmail(),
                'sessionId' => $session->getId(),
            ]);
        }
    }
}
