<?php

namespace App\Service;

class AdminReportExportService
{
    public function buildExcelXml(array $reportData): string
    {
        $xml = [];
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<?mso-application progid="Excel.Sheet"?>';
        $xml[] = '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml[] = '<Styles>';
        $xml[] = '<Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Borders/><Font ss:FontName="Calibri" ss:Size="11"/><Interior/><NumberFormat/><Protection/></Style>';
        $xml[] = '<Style ss:ID="Header"><Font ss:Bold="1"/><Interior ss:Color="#E5F4EE" ss:Pattern="Solid"/></Style>';
        $xml[] = '</Styles>';

        $xml[] = $this->sheetComplete(
            $reportData['stats'] ?? [],
            $reportData['reservationsByStatus'] ?? [],
            $reportData['hotels'] ?? [],
            $reportData['chambres'] ?? [],
            $reportData['reservations'] ?? []
        );
        $xml[] = $this->sheetSummary($reportData['stats'] ?? [], $reportData['reservationsByStatus'] ?? []);
        $xml[] = $this->sheetHotels($reportData['hotels'] ?? []);
        $xml[] = $this->sheetChambres($reportData['chambres'] ?? []);
        $xml[] = $this->sheetReservations($reportData['reservations'] ?? []);

        $xml[] = '</Workbook>';

        return implode('', $xml);
    }

    private function sheetSummary(array $stats, array $statusStats): string
    {
        $rows = [];
        $rows[] = $this->row(['Indicateur', 'Valeur'], true);
        $rows[] = $this->row(["Nombre d'hotels", (string) ($stats['totalHotels'] ?? 0)]);
        $rows[] = $this->row(['Nombre de chambres', (string) ($stats['totalChambres'] ?? 0)]);
        $rows[] = $this->row(['Nombre de reservations', (string) ($stats['totalReservations'] ?? 0)]);

        foreach ($statusStats as $status => $count) {
            $rows[] = $this->row(['Reservations ' . $status, (string) $count]);
        }

        return '<Worksheet ss:Name="Synthese"><Table>' . implode('', $rows) . '</Table></Worksheet>';
    }

    private function sheetComplete(array $stats, array $statusStats, array $hotels, array $chambres, array $reservations): string
    {
        $rows = [];

        $rows[] = $this->row(['Synthese'], true);
        $rows[] = $this->row(['Indicateur', 'Valeur'], true);
        $rows[] = $this->row(["Nombre d'hotels", (string) ($stats['totalHotels'] ?? 0)]);
        $rows[] = $this->row(['Nombre de chambres', (string) ($stats['totalChambres'] ?? 0)]);
        $rows[] = $this->row(['Nombre de reservations', (string) ($stats['totalReservations'] ?? 0)]);
        foreach ($statusStats as $status => $count) {
            $rows[] = $this->row(['Reservations ' . $status, (string) $count]);
        }

        $rows[] = $this->row(['']);
        $rows[] = $this->row(['Hotels'], true);
        $rows[] = $this->row(['ID', 'Nom', 'Ville', 'Adresse', 'Nombre etoiles', 'Budget', 'Description', 'Photo URL', 'Favori', 'Nombre de chambres', 'Cree le', 'Modifie le'], true);
        foreach ($hotels as $hotel) {
            $rows[] = $this->row([
                (string) ($hotel['id'] ?? ''),
                (string) ($hotel['nom'] ?? ''),
                (string) ($hotel['ville'] ?? ''),
                (string) ($hotel['adresse'] ?? ''),
                (string) ($hotel['nombreEtoiles'] ?? ''),
                (string) ($hotel['budget'] ?? ''),
                (string) ($hotel['description'] ?? ''),
                (string) ($hotel['photoUrl'] ?? ''),
                $this->formatBoolean($hotel['isFavoris'] ?? false),
                (string) ($hotel['nombreChambres'] ?? 0),
                $this->formatDateTime($hotel['createdAt'] ?? null),
                $this->formatDateTime($hotel['updatedAt'] ?? null),
            ]);
        }

        $rows[] = $this->row(['']);
        $rows[] = $this->row(['Chambres'], true);
        $rows[] = $this->row(['ID', 'Hotel ID', 'Hotel', 'Type', 'Capacite', 'Nombre chambres', 'Equipements', 'Prix standard', 'Prix haute saison', 'Prix basse saison', 'Cree le', 'Modifie le'], true);
        foreach ($chambres as $chambre) {
            $rows[] = $this->row([
                (string) ($chambre['id'] ?? ''),
                (string) ($chambre['hotelId'] ?? ''),
                (string) ($chambre['hotelNom'] ?? ''),
                (string) ($chambre['type'] ?? ''),
                (string) ($chambre['capacite'] ?? ''),
                (string) ($chambre['nombreDeChambres'] ?? ''),
                (string) ($chambre['equipements'] ?? ''),
                (string) ($chambre['prixStandard'] ?? ''),
                (string) ($chambre['prixHauteSaison'] ?? ''),
                (string) ($chambre['prixBasseSaison'] ?? ''),
                $this->formatDateTime($chambre['createdAt'] ?? null),
                $this->formatDateTime($chambre['updatedAt'] ?? null),
            ]);
        }

        $rows[] = $this->row(['']);
        $rows[] = $this->row(['Reservations'], true);
        $rows[] = $this->row(['ID', 'Utilisateur ID', 'Client', 'Email client', 'Hotel ID', 'Hotel', 'Chambre ID', 'Chambre', 'Date debut', 'Date fin', 'Prix total', 'Statut', 'Cree le', 'Modifie le'], true);
        foreach ($reservations as $reservation) {
            $rows[] = $this->row([
                (string) ($reservation['id'] ?? ''),
                (string) ($reservation['userId'] ?? ''),
                trim((string) ($reservation['clientNom'] ?? '')),
                (string) ($reservation['clientEmail'] ?? ''),
                (string) ($reservation['hotelId'] ?? ''),
                (string) ($reservation['hotelNom'] ?? ''),
                (string) ($reservation['chambreId'] ?? ''),
                (string) ($reservation['chambreType'] ?? ''),
                $this->formatDate($reservation['dateDebut'] ?? null),
                $this->formatDate($reservation['dateFin'] ?? null),
                (string) ($reservation['prixTotal'] ?? ''),
                (string) ($reservation['statut'] ?? ''),
                $this->formatDateTime($reservation['createdAt'] ?? null),
                $this->formatDateTime($reservation['updatedAt'] ?? null),
            ]);
        }

        return '<Worksheet ss:Name="Rapport complet"><Table>' . implode('', $rows) . '</Table></Worksheet>';
    }

    private function sheetHotels(array $hotels): string
    {
        $rows = [];
        $rows[] = $this->row(['ID', 'Nom', 'Ville', 'Adresse', 'Nombre etoiles', 'Budget', 'Description', 'Photo URL', 'Favori', 'Nombre de chambres', 'Cree le', 'Modifie le'], true);

        foreach ($hotels as $hotel) {
            $rows[] = $this->row([
                (string) ($hotel['id'] ?? ''),
                (string) ($hotel['nom'] ?? ''),
                (string) ($hotel['ville'] ?? ''),
                (string) ($hotel['adresse'] ?? ''),
                (string) ($hotel['nombreEtoiles'] ?? ''),
                (string) ($hotel['budget'] ?? ''),
                (string) ($hotel['description'] ?? ''),
                (string) ($hotel['photoUrl'] ?? ''),
                $this->formatBoolean($hotel['isFavoris'] ?? false),
                (string) ($hotel['nombreChambres'] ?? 0),
                $this->formatDateTime($hotel['createdAt'] ?? null),
                $this->formatDateTime($hotel['updatedAt'] ?? null),
            ]);
        }

        return '<Worksheet ss:Name="Hotels"><Table>' . implode('', $rows) . '</Table></Worksheet>';
    }

    private function sheetChambres(array $chambres): string
    {
        $rows = [];
        $rows[] = $this->row(['ID', 'Hotel ID', 'Hotel', 'Type', 'Capacite', 'Nombre chambres', 'Equipements', 'Prix standard', 'Prix haute saison', 'Prix basse saison', 'Cree le', 'Modifie le'], true);

        foreach ($chambres as $chambre) {
            $rows[] = $this->row([
                (string) ($chambre['id'] ?? ''),
                (string) ($chambre['hotelId'] ?? ''),
                (string) ($chambre['hotelNom'] ?? ''),
                (string) ($chambre['type'] ?? ''),
                (string) ($chambre['capacite'] ?? ''),
                (string) ($chambre['nombreDeChambres'] ?? ''),
                (string) ($chambre['equipements'] ?? ''),
                (string) ($chambre['prixStandard'] ?? ''),
                (string) ($chambre['prixHauteSaison'] ?? ''),
                (string) ($chambre['prixBasseSaison'] ?? ''),
                $this->formatDateTime($chambre['createdAt'] ?? null),
                $this->formatDateTime($chambre['updatedAt'] ?? null),
            ]);
        }

        return '<Worksheet ss:Name="Chambres"><Table>' . implode('', $rows) . '</Table></Worksheet>';
    }

    private function sheetReservations(array $reservations): string
    {
        $rows = [];
        $rows[] = $this->row(['ID', 'Utilisateur ID', 'Client', 'Email client', 'Hotel ID', 'Hotel', 'Chambre ID', 'Chambre', 'Date debut', 'Date fin', 'Prix total', 'Statut', 'Cree le', 'Modifie le'], true);

        foreach ($reservations as $reservation) {
            $dateDebut = $this->formatDate($reservation['dateDebut'] ?? null);
            $dateFin = $this->formatDate($reservation['dateFin'] ?? null);

            $rows[] = $this->row([
                (string) ($reservation['id'] ?? ''),
                (string) ($reservation['userId'] ?? ''),
                trim((string) ($reservation['clientNom'] ?? '')),
                (string) ($reservation['clientEmail'] ?? ''),
                (string) ($reservation['hotelId'] ?? ''),
                (string) ($reservation['hotelNom'] ?? ''),
                (string) ($reservation['chambreId'] ?? ''),
                (string) ($reservation['chambreType'] ?? ''),
                $dateDebut,
                $dateFin,
                (string) ($reservation['prixTotal'] ?? ''),
                (string) ($reservation['statut'] ?? ''),
                $this->formatDateTime($reservation['createdAt'] ?? null),
                $this->formatDateTime($reservation['updatedAt'] ?? null),
            ]);
        }

        return '<Worksheet ss:Name="Reservations"><Table>' . implode('', $rows) . '</Table></Worksheet>';
    }

    private function row(array $cells, bool $header = false): string
    {
        $xmlCells = [];
        foreach ($cells as $value) {
            $style = $header ? ' ss:StyleID="Header"' : '';
            $xmlCells[] = '<Cell' . $style . '><Data ss:Type="String">' . $this->escapeXml((string) $value) . '</Data></Cell>';
        }

        return '<Row>' . implode('', $xmlCells) . '</Row>';
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function formatDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return (string) ($value ?? '');
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) ($value ?? '');
    }

    private function formatBoolean(mixed $value): string
    {
        return (bool) $value ? 'Oui' : 'Non';
    }
}
