<?php

namespace App\Service;

use App\Entity\Location;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class LocationPdfService
{
    private Environment $twig;
    private string $projectDir;

    public function __construct(Environment $twig, string $projectDir)
    {
        $this->twig = $twig;
        $this->projectDir = $projectDir;
    }

    public function generatePdf(Location $location): Response
    {
        $html = $this->twig->render('location/pdf_template.html.twig', [
            'location' => $location,
            'dateGeneration' => new \DateTime(),
            'stats' => [
                'nbJours' => $location->getNbJours(),
                'prixJournier' => round(floatval($location->getMontantTotal()) / $location->getNbJours(), 2),
                'prixTotal' => floatval($location->getMontantTotal()),
            ]
        ]);
        return new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="location-' . $location->getReference() . '.html"',
        ]);
    }
}
