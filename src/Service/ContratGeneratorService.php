<?php

namespace App\Service;

use App\Entity\Location;
use Twig\Environment;

class ContratGeneratorService
{
    private string $projectDir;
    private Environment $twig;

    public function __construct(string $projectDir, Environment $twig)
    {
        $this->projectDir = $projectDir;
        $this->twig = $twig;
    }

    public function generateContrat(Location $location): string
    {
        // Créer le répertoire s'il n'existe pas
        $uploadDir = $this->projectDir . '/public/uploads/contrats';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Générer le HTML du contrat
        $html = $this->twig->render('location/contrat_template.html.twig', [
            'location' => $location,
            'dateGeneration' => new \DateTime(),
        ]);

        // Convertir en PDF
        $fileName = 'contrat-' . $location->getReference() . '.pdf';
        $filePath = $uploadDir . '/' . $fileName;

        return $this->createHtmlFallback($location, $uploadDir, $fileName);
    }

    private function createHtmlFallback(Location $location, string $uploadDir, string $fileName): string
    {
        // Créer une version HTML simple si PDF n'est pas disponible
        $htmlFileName = str_replace('.pdf', '.html', $fileName);
        $htmlFilePath = $uploadDir . '/' . $htmlFileName;

        $html = $this->twig->render('location/contrat_template.html.twig', [
            'location' => $location,
            'dateGeneration' => new \DateTime(),
        ]);

        file_put_contents($htmlFilePath, $html);
        return 'uploads/contrats/' . $htmlFileName;
    }
}
