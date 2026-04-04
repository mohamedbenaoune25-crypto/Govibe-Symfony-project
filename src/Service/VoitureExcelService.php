<?php

namespace App\Service;

use App\Entity\Voiture;
use App\Repository\VoitureRepository;

class VoitureExcelService
{
    public function __construct(private VoitureRepository $voitureRepository)
    {
    }

    public function exportAllVoitures(): string
    {
        $voitures = $this->voitureRepository->findAll();

        $rows = [[
            'Matricule',
            'Marque',
            'Modèle',
            'Année',
            'Carburant',
            'Prix/Jour (DT)',
            'Adresse Agence',
            'Latitude',
            'Longitude',
            'Statut',
            'Date Création',
        ]];

        foreach ($voitures as $voiture) {
            $rows[] = [
                $voiture->getMatricule() ?? '',
                $voiture->getMarque() ?? '',
                $voiture->getModele() ?? '',
                (string) ($voiture->getAnnee() ?? ''),
                $voiture->getTypeCarburant() ?? '',
                number_format((float) ($voiture->getPrixJour() ?? 0), 2, '.', ''),
                $voiture->getAdresseAgence() ?? '',
                $voiture->getLatitude() ?? '',
                $voiture->getLongitude() ?? '',
                $this->getStatutLabel($voiture->getStatut() ?? ''),
                $voiture->getDateCreation() ? $voiture->getDateCreation()->format('d/m/Y H:i') : '',
            ];
        }

        return $this->buildCsv($rows);
    }

    public function exportSingleVoiture(Voiture $voiture): string
    {
        $rows = [
            ['Champ', 'Valeur'],
            ['Matricule', $voiture->getMatricule() ?? ''],
            ['Marque', $voiture->getMarque() ?? ''],
            ['Modèle', $voiture->getModele() ?? ''],
            ['Année', (string) ($voiture->getAnnee() ?? '')],
            ['Carburant', $voiture->getTypeCarburant() ?? ''],
            ['Prix/Jour (DT)', number_format((float) ($voiture->getPrixJour() ?? 0), 2, '.', '')],
            ['Adresse Agence', $voiture->getAdresseAgence() ?? ''],
            ['Latitude', $voiture->getLatitude() ?? ''],
            ['Longitude', $voiture->getLongitude() ?? ''],
            ['Statut', $this->getStatutLabel($voiture->getStatut() ?? '')],
            ['Description', $voiture->getDescription() ?? ''],
            ['Date Création', $voiture->getDateCreation() ? $voiture->getDateCreation()->format('d/m/Y H:i') : ''],
        ];

        return $this->buildCsv($rows);
    }

    private function buildCsv(array $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($stream, $row, ';');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF" . ($csv ?: '');
    }

    private function getStatutLabel(string $statut): string
    {
        return match ($statut) {
            'DISPONIBLE' => 'Disponible',
            'EN_MAINTENANCE' => 'En Maintenance',
            'ACCIDENTE' => 'Accidentée',
            default => $statut,
        };
    }
}
