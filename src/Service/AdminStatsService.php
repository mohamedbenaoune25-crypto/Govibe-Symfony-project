<?php

namespace App\Service;

use App\Repository\ChambreRepository;
use App\Repository\HotelRepository;
use App\Repository\ReservationRepository;

class AdminStatsService
{
    public function __construct(
        private readonly HotelRepository $hotelRepository,
        private readonly ChambreRepository $chambreRepository,
        private readonly ReservationRepository $reservationRepository
    ) {
    }

    public function getHotelReservationStats(): array
    {
        $totalHotels = $this->hotelRepository->count([]);
        $totalChambres = $this->chambreRepository->count([]);
        $totalReservations = $this->reservationRepository->count([]);

        return [
            'totalHotels' => $totalHotels,
            'totalChambres' => $totalChambres,
            'totalReservations' => $totalReservations,
        ];
    }

    public function getDetailedReportData(): array
    {
        $stats = $this->getHotelReservationStats();

        $hotels = $this->hotelRepository->createQueryBuilder('h')
            ->select('h.id AS id, h.nom AS nom, h.ville AS ville, h.adresse AS adresse, h.nombreEtoiles AS nombreEtoiles, h.budget AS budget, h.description AS description, h.photoUrl AS photoUrl, h.createdAt AS createdAt, h.updatedAt AS updatedAt, h.isFavoris AS isFavoris, COUNT(c.id) AS nombreChambres')
            ->leftJoin('h.chambres', 'c')
            ->groupBy('h.id')
            ->orderBy('h.nom', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $chambres = $this->chambreRepository->createQueryBuilder('c')
            ->select('c.id AS id, c.type AS type, c.capacite AS capacite, c.nombreDeChambres AS nombreDeChambres, c.equipements AS equipements, c.prixStandard AS prixStandard, c.prixHauteSaison AS prixHauteSaison, c.prixBasseSaison AS prixBasseSaison, c.createdAt AS createdAt, c.updatedAt AS updatedAt, h.id AS hotelId, h.nom AS hotelNom')
            ->leftJoin('c.hotel', 'h')
            ->orderBy('h.nom', 'ASC')
            ->addOrderBy('c.type', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $reservations = $this->reservationRepository->createQueryBuilder('r')
            ->select("r.id AS id, r.dateDebut AS dateDebut, r.dateFin AS dateFin, r.prixTotal AS prixTotal, r.statut AS statut, r.createdAt AS createdAt, r.updatedAt AS updatedAt, h.id AS hotelId, h.nom AS hotelNom, c.id AS chambreId, c.type AS chambreType, u.id AS userId, u.email AS clientEmail, CONCAT(COALESCE(u.prenom, ''), CONCAT(' ', COALESCE(u.nom, ''))) AS clientNom")
            ->leftJoin('r.hotel', 'h')
            ->leftJoin('r.chambre', 'c')
            ->leftJoin('r.user', 'u')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $statusRows = $this->reservationRepository->createQueryBuilder('r')
            ->select('r.statut AS statut, COUNT(r.id) AS total')
            ->groupBy('r.statut')
            ->getQuery()
            ->getArrayResult();

        $reservationsByStatus = [
            'EN_ATTENTE' => 0,
            'CONFIRMEE' => 0,
            'ANNULEE' => 0,
        ];

        foreach ($statusRows as $row) {
            $status = strtoupper((string) ($row['statut'] ?? ''));
            if (!array_key_exists($status, $reservationsByStatus)) {
                $reservationsByStatus[$status] = 0;
            }
            $reservationsByStatus[$status] = (int) ($row['total'] ?? 0);
        }

        return $this->normalizeArray([
            'stats' => $stats,
            'hotels' => $hotels,
            'chambres' => $chambres,
            'reservations' => $reservations,
            'reservationsByStatus' => $reservationsByStatus,
        ]);
    }

    private function normalizeArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->normalizeArray($value);
                continue;
            }

            if (is_string($value)) {
                $data[$key] = $this->normalizeString($value);
            }
        }

        return $data;
    }

    private function normalizeString(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $normalized = $this->forceValidUtf8($value);

        // Remove non-printable ASCII control characters that can break exports.
        $normalized = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $normalized);

        return $normalized;
    }

    private function forceValidUtf8(string $value): string
    {
        $json = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return '';
        }

        $decoded = json_decode($json, true);
        if (!is_string($decoded)) {
            return '';
        }

        return $decoded;
    }
}
