<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Psr\Log\LoggerInterface;

class LoginSuccessSubscriber implements EventSubscriberInterface
{
    private UrlGeneratorInterface $urlGenerator;
    private LoggerInterface $logger;

    public function __construct(UrlGeneratorInterface $urlGenerator, LoggerInterface $logger)
    {
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
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

        $this->logger->info(sprintf('User %s connected with roles: %s', $user->getUserIdentifier(), implode(', ', $roles)));

        // By default, Symfony redirects to where the user wanted to go before being asked to log in.
        // We retrieve the target path from the session if it exists.
        $request = $event->getRequest();

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
