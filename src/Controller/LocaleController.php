<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class LocaleController extends AbstractController
{
    private const SUPPORTED_LOCALES = ['fr', 'en', 'ar', 'de', 'it', 'es'];

    #[Route('/locale/{locale}', name: 'app_locale_switch', methods: ['GET'])]
    public function switchLocale(string $locale, Request $request): RedirectResponse
    {
        $locale = strtolower(trim($locale));
        if (!in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $locale = 'fr';
        }

        if ($request->hasSession()) {
            $request->getSession()->set('_locale', $locale);
        }

        $referer = (string) $request->headers->get('referer', '');
        if ($referer !== '' && str_starts_with($referer, $request->getSchemeAndHttpHost())) {
            return new RedirectResponse($referer);
        }

        return $this->redirectToRoute('app_user_home');
    }
}
