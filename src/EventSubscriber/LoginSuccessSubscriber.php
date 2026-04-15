<?php

namespace App\EventSubscriber;

use App\Service\UserSessionService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Psr\Log\LoggerInterface;

class LoginSuccessSubscriber implements EventSubscriberInterface
{
    private UrlGeneratorInterface $urlGenerator;
    private LoggerInterface $logger;
    private UserSessionService $sessionService;

    public function __construct(
        UrlGeneratorInterface $urlGenerator,
        LoggerInterface $logger,
        UserSessionService $sessionService
    ) {
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
        $this->sessionService = $sessionService;
    }

    public static function getSubscribedEvents(): array
    {
        // Prio 0 is fine, after authentication token is updated
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        $roles = $user->getRoles();
        $request = $event->getRequest();

        $this->logger->info(sprintf('User %s connected with roles: %s', $user->getUserIdentifier(), implode(', ', $roles)));

        // Create a session record for this login (if not already created by the authenticator)
        // The AdaptiveMFAAuthenticator already creates sessions for LOW risk,
        // and the SecurityController creates them for MFA-verified logins.
        // This handles OAuth logins (Google) which bypass the custom authenticator.
        try {
            $this->sessionService->createSession($user, $request);
            $this->logger->info('[LoginSuccess] Session created for OAuth/external login', [
                'user' => $user->getUserIdentifier(),
            ]);
        } catch (\Throwable $e) {
            // Don't block login on session creation failure
            $this->logger->error('[LoginSuccess] Failed to create session', [
                'error' => $e->getMessage(),
            ]);
        }

        // If the user is an admin, redirect directly strictly to the admin dashboard
        if (in_array('ROLE_ADMIN', $roles, true)) {
            $response = new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
            $event->setResponse($response);
            return;
        }

        // If the user is a simple user, redirect strictly to user home ('app_user_home')
        $response = new RedirectResponse($this->urlGenerator->generate('app_user_home'));
        $event->setResponse($response);
    }
}
