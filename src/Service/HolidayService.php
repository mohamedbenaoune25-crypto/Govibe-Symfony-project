<?php

namespace App\Service;

use DateTimeImmutable;

class HolidayService
{
    private array $holidays = [];
    private float $supplementPercentage = 0;

    public function __construct(string $projectDir)
    {
        $this->loadHolidays($projectDir);
    }

    private function loadHolidays(string $projectDir): void
    {
        $filePath = $projectDir . '/config/holidays.json';

        if (!file_exists($filePath)) {
            return;
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        // Convertir dates en DateTimeImmutable pour comparaison
        $this->holidays = [];
        foreach ($data['holidays'] ?? [] as $holiday) {
            try {
                $date = DateTimeImmutable::createFromFormat('Y-m-d', $holiday['date']);
                if ($date !== false) {
                    $this->holidays[$date->format('Y-m-d')] = $holiday['name'];
                }
            } catch (\Exception) {
                // Ignorer les dates invalides
            }
        }

        $this->supplementPercentage = (float) ($data['supplementPercentage'] ?? 0);
    }

    /**
     * Obtient le pourcentage de supplément pour jours fériés
     */
    public function getSupplementPercentage(): float
    {
        return $this->supplementPercentage;
    }

    /**
     * Définit le pourcentage de supplément (pour l'admin)
     */
    public function setSupplementPercentage(float $percentage): void
    {
        $this->supplementPercentage = $percentage;
    }

    /**
     * Compte les nuits qui tombent sur un jour férié
     *
     * @param DateTimeImmutable $startDate Date de début (incluse)
     * @param DateTimeImmutable $endDate   Date de fin (exclue)
     * @return int Nombre de nuits sur jours fériés
     */
    public function countHolidayNights(DateTimeImmutable $startDate, DateTimeImmutable $endDate): int
    {
        $count = 0;
        $current = $startDate;

        while ($current < $endDate) {
            $dateStr = $current->format('Y-m-d');
            if (isset($this->holidays[$dateStr])) {
                $count++;
            }
            $current = $current->modify('+1 day');
        }

        return $count;
    }

    /**
     * Calcule le supplément total pour les jours fériés
     *
     * @param float $pricePerNight Prix par nuit
     * @param DateTimeImmutable $startDate Date de début
     * @param DateTimeImmutable $endDate   Date de fin
     * @return float Montant du supplément
     */
    public function calculateHolidaySupplement(float $pricePerNight, DateTimeImmutable $startDate, DateTimeImmutable $endDate): float
    {
        $holidayNights = $this->countHolidayNights($startDate, $endDate);
        if ($holidayNights === 0) {
            return 0.0;
        }

        $supplementAmount = $pricePerNight * ($this->supplementPercentage / 100);
        return round($holidayNights * $supplementAmount, 2);
    }

    /**
     * Obtient la liste des jours fériés
     */
    public function getHolidays(): array
    {
        return $this->holidays;
    }

    /**
     * Ajoute un jour férié
     */
    public function addHoliday(string $date, string $name): void
    {
        try {
            $dateObj = DateTimeImmutable::createFromFormat('Y-m-d', $date);
            if ($dateObj !== false) {
                $this->holidays[$dateObj->format('Y-m-d')] = $name;
            }
        } catch (\Exception) {
            // Ignorer les dates invalides
        }
    }

    /**
     * Supprime un jour férié
     */
    public function removeHoliday(string $date): void
    {
        unset($this->holidays[$date]);
    }

    /**
     * Sauvegarde les jours fériés dans le fichier JSON
     */
    public function saveHolidays(string $projectDir): bool
    {
        $filePath = $projectDir . '/config/holidays.json';

        $data = [
            'holidays' => array_map(
                fn($date, $name) => ['date' => $date, 'name' => $name],
                array_keys($this->holidays),
                array_values($this->holidays)
            ),
            'supplementPercentage' => $this->supplementPercentage,
        ];

        // Trier par date
        usort($data['holidays'], fn($a, $b) => $a['date'] <=> $b['date']);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return file_put_contents($filePath, $json) !== false;
    }
}
