<?php

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_user_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            // On pourra passer les activités du moment ou statistiques réelles si configuré
        ]);
    }
}
