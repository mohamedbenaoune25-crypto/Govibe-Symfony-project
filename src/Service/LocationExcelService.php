<?php

namespace App\Service;

use App\Entity\Location;
use Symfony\Component\HttpFoundation\Response;

class LocationExcelService
{
    public function generateExcel(Location $location): Response
    {
        $voiture = $location->getVoiture();
        $user = $location->getUser();

        $rows = [
            ['Champ', 'Valeur'],
            ['Référence', $location->getReference() ?? ''],
            ['Statut', $location->getStatut() ?? ''],
            ['Client', trim(($user?->getPrenom() ?? '') . ' ' . ($user?->getNom() ?? ''))],
            ['Email Client', $user?->getEmail() ?? ''],
            ['Marque', $voiture?->getMarque() ?? ''],
            ['Modèle', $voiture?->getModele() ?? ''],
            ['Matricule', $voiture?->getMatricule() ?? ''],
            ['Année', (string) ($voiture?->getAnnee() ?? '')],
            ['Type Carburant', $voiture?->getTypeCarburant() ?? ''],
            ['Date Début', $location->getDateDebut() ? $location->getDateDebut()->format('d/m/Y') : ''],
            ['Date Fin', $location->getDateFin() ? $location->getDateFin()->format('d/m/Y') : ''],
            ['Nombre de jours', (string) ($location->getNbJours() ?? '')],
            ['Prix/Jour', number_format((float) ($voiture?->getPrixJour() ?? 0), 2, '.', '') . ' DT'],
            ['Montant Total', number_format((float) ($location->getMontantTotal() ?? 0), 2, '.', '') . ' DT'],
            ['Date Création', $location->getDateCreation() ? $location->getDateCreation()->format('d/m/Y H:i') : ''],
        ];

        $content = $this->buildCsv($rows);
        $fileName = 'location-' . ($location->getReference() ?? 'detail') . '.csv';

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    public function generateStatsExcel(array $locations): Response
    {
        $rows = [[
            'Référence',
            'Voiture',
            'Date Début',
            'Date Fin',
            'Jours',
            'Prix/Jour',
            'Montant',
            'Statut',
        ]];

        $totalRevenue = 0.0;

        foreach ($locations as $location) {
            $voiture = $location->getVoiture();
            $montant = (float) ($location->getMontantTotal() ?? 0);
            $totalRevenue += $montant;

            $rows[] = [
                $location->getReference() ?? '',
                trim(($voiture?->getMarque() ?? '') . ' ' . ($voiture?->getModele() ?? '')),
                $location->getDateDebut() ? $location->getDateDebut()->format('d/m/Y') : '',
                $location->getDateFin() ? $location->getDateFin()->format('d/m/Y') : '',
                (string) ($location->getNbJours() ?? ''),
                number_format((float) ($voiture?->getPrixJour() ?? 0), 2, '.', '') . ' DT',
                number_format($montant, 2, '.', '') . ' DT',
                $location->getStatut() ?? '',
            ];
        }

        $rows[] = [];
        $rows[] = ['TOTAL', '', '', '', '', '', number_format($totalRevenue, 2, '.', '') . ' DT', ''];

        $content = $this->buildCsv($rows);

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="statistiques-locations.csv"',
        ]);
    }

    public function generateAdminStatsExcel(array $locations): Response
    {
        $rows = [[
            'Référence',
            'Client',
            'Email',
            'Voiture',
            'Début',
            'Fin',
            'Jours',
            'Prix/Jour',
            'Montant',
            'Statut',
        ]];

        $totalRevenue = 0.0;

        foreach ($locations as $location) {
            $voiture = $location->getVoiture();
            $user = $location->getUser();
            $montant = (float) ($location->getMontantTotal() ?? 0);
            $totalRevenue += $montant;

            $rows[] = [
                $location->getReference() ?? '',
                trim(($user?->getPrenom() ?? '') . ' ' . ($user?->getNom() ?? '')),
                $user?->getEmail() ?? '',
                trim(($voiture?->getMarque() ?? '') . ' ' . ($voiture?->getModele() ?? '')) . ' (' . ($voiture?->getMatricule() ?? '') . ')',
                $location->getDateDebut() ? $location->getDateDebut()->format('d/m/Y') : '',
                $location->getDateFin() ? $location->getDateFin()->format('d/m/Y') : '',
                (string) ($location->getNbJours() ?? ''),
                number_format((float) ($voiture?->getPrixJour() ?? 0), 2, '.', '') . ' DT',
                number_format($montant, 2, '.', '') . ' DT',
                $location->getStatut() ?? '',
            ];
        }

        $rows[] = [];
        $rows[] = ['TOTAL', '', '', '', '', '', '', '', number_format($totalRevenue, 2, '.', '') . ' DT', ''];

        $content = $this->buildCsv($rows);

        return new Response($content, Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rapport-locations-admin.csv"',
        ]);
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
}
