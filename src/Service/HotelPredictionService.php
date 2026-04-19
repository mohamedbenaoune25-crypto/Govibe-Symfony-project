<?php

namespace App\Service;

use App\Repository\ReservationRepository;

class HotelPredictionService
{
    /** @var string[] */
    private const RELEVANT_STATUSES = ['ACCEPTEE', 'CONFIRMEE', 'EN_ATTENTE'];
    private const ALERT_HISTORY_MONTHS = 12;
    private const ALERT_MIN_TOTAL = 4;
    private const ALERT_TRIGGER_PCT = 8.0;
    private const ALERT_MIN_CONFIDENCE = 30;

    public function __construct(
        private readonly ReservationRepository $reservationRepository
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPredictionDashboard(string $locale = 'fr'): array
    {
        $today = new \DateTimeImmutable('today');
        $locale = strtolower(trim($locale)) ?: 'fr';

        $topHotels = $this->buildTopHotels($today);
        $topRooms = $this->buildTopRooms($today);
        $history = $this->buildMonthlyHistory($today, 6, $locale);
        $historyYear = $this->buildMonthlyHistory($today, 12, $locale);

        $forecast = $this->forecastNextMonths($history['points'], 3);
        $forecastYear = $this->forecastNextMonths($historyYear['points'], 12);
        $trendDirection = $this->detectTrendDirection($history['points']);
        $pastReservations = array_sum($history['points']);
        $predictedReservations = array_sum(array_column($forecast, 'value'));
        $predictedReservationsYear = array_sum(array_column($forecastYear, 'value'));
        $recentThreeMonths = array_sum(array_slice($history['points'], -3));
        $predictedTopHotelsYear = $this->predictTopHotelsForNextYear($today, $predictedReservationsYear);
        $predictedTopRoomTypesYear = $this->predictTopRoomTypesForNextYear($today, $predictedReservationsYear);
        $globalYearlyProjection = $this->buildGlobalYearlyProjection($today, $locale);
        $annualForecastConfidence = $this->computeForecastConfidence($historyYear['points'], array_column($forecastYear, 'value'));

        $forecastGrowthPct = $recentThreeMonths > 0
            ? round((($predictedReservations - $recentThreeMonths) / $recentThreeMonths) * 100, 1)
            : 0.0;

        $leaderHotelSharePct = ($pastReservations > 0 && isset($topHotels[0]))
            ? round((((int) $topHotels[0]['reservationsCount']) / $pastReservations) * 100, 1)
            : 0.0;

        $leaderRoomSharePct = ($pastReservations > 0 && isset($topRooms[0]))
            ? round((((int) $topRooms[0]['reservationsCount']) / $pastReservations) * 100, 1)
            : 0.0;

        $anomalies = $this->detectAnomalies($today);

        return [
            'topHotels' => $topHotels,
            'topRooms' => $topRooms,
            'history' => $history,
            'forecast' => $forecast,
            'forecastYear' => $forecastYear,
            'trendDirection' => $trendDirection,
            'totals' => [
                'pastReservations' => $pastReservations,
                'predictedReservations' => $predictedReservations,
                'predictedReservationsYear' => $predictedReservationsYear,
                'analyzedMonths' => count($history['labels']),
                'recentThreeMonths' => $recentThreeMonths,
                'annualForecastConfidence' => $annualForecastConfidence['score'],
                'annualForecastConfidenceLabel' => $annualForecastConfidence['label'],
            ],
            'historyMaxPoint' => max(1, ...$history['points']),
            'historyYear' => $historyYear,
            'predictedTopHotelsYear' => $predictedTopHotelsYear,
            'predictedTopRoomTypesYear' => $predictedTopRoomTypesYear,
            'globalYearlyProjection' => $globalYearlyProjection,
            'anomalies' => $anomalies,
            'insights' => [
                'forecastGrowthPct' => $forecastGrowthPct,
                'leaderHotelSharePct' => $leaderHotelSharePct,
                'leaderRoomSharePct' => $leaderRoomSharePct,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function detectAnomalies(\DateTimeImmutable $today): array
    {
        $analysisStart = $today->modify('first day of -'.(self::ALERT_HISTORY_MONTHS - 1).' months')->setTime(0, 0);
        $monthKeys = $this->buildTimelineMonthKeys($analysisStart, self::ALERT_HISTORY_MONTHS);

        $rows = $this->reservationRepository->createQueryBuilder('r')
            ->select('h.id AS hotelId, h.nom AS hotelNom, h.nombreEtoiles AS nombreEtoiles, r.dateDebut AS dateDebut, COUNT(r.id) AS count')
            ->leftJoin('r.hotel', 'h')
            ->andWhere('r.statut IN (:statuses)')
            ->andWhere('r.dateDebut >= :analysisStart')
            ->andWhere('r.dateDebut <= :today')
            ->setParameter('statuses', self::RELEVANT_STATUSES)
            ->setParameter('analysisStart', $analysisStart)
            ->setParameter('today', $today)
            ->groupBy('h.id')
            ->addGroupBy('h.nom')
            ->addGroupBy('h.nombreEtoiles')
            ->addGroupBy('r.dateDebut')
            ->orderBy('r.dateDebut', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $hotelData = [];
        foreach ($rows as $row) {
            $hotelId = (int) ($row['hotelId'] ?? 0);
            if ($hotelId <= 0) {
                continue;
            }

            if (!isset($hotelData[$hotelId])) {
                $hotelData[$hotelId] = [
                    'hotelId' => $hotelId,
                    'hotelNom' => (string) ($row['hotelNom'] ?? ''),
                    'nombreEtoiles' => (int) ($row['nombreEtoiles'] ?? 0),
                    'monthlyData' => array_fill_keys($monthKeys, 0),
                ];
            }

            $monthKey = $this->extractMonthKey($row['dateDebut'] ?? null);
            if ($monthKey !== null && array_key_exists($monthKey, $hotelData[$hotelId]['monthlyData'])) {
                $hotelData[$hotelId]['monthlyData'][$monthKey] = ($hotelData[$hotelId]['monthlyData'][$monthKey] ?? 0) + (int) ($row['count'] ?? 0);
            }
        }

        $anomalies = [];
        foreach ($hotelData as $data) {
            $monthly = array_values($data['monthlyData']);
            $monthlyTotal = array_sum($monthly);
            if ($monthlyTotal < self::ALERT_MIN_TOTAL) {
                continue;
            }

            $monthlyCount = count($monthly);
            if ($monthlyCount < 2) {
                continue;
            }

            $recentMonth = (int) ($monthly[$monthlyCount - 1] ?? 0);
            $previousMonth = (int) ($monthly[$monthlyCount - 2] ?? 0);
            $baselineWindow = array_slice($monthly, max(0, $monthlyCount - 7), 6);
            $baselineAverage = count($baselineWindow) > 0 ? array_sum($baselineWindow) / count($baselineWindow) : 0.0;

            if ($recentMonth === 0 && $previousMonth === 0 && $baselineAverage <= 0.0) {
                continue;
            }

            $variationPrev = $previousMonth > 0
                ? (($recentMonth - $previousMonth) / $previousMonth) * 100
                : ($recentMonth > 0 ? 100.0 : 0.0);

            $variationBaseline = $baselineAverage > 0.0
                ? (($recentMonth - $baselineAverage) / $baselineAverage) * 100
                : ($recentMonth > 0 ? 100.0 : 0.0);

            $absoluteVariation = max(abs($variationPrev), abs($variationBaseline));
            if ($absoluteVariation < self::ALERT_TRIGGER_PCT) {
                continue;
            }

            $reference = max(1.0, $baselineAverage > 0.0 ? $baselineAverage : (float) $previousMonth);
            $confidence = $this->computeAlertConfidence($absoluteVariation, $monthlyTotal, abs($recentMonth - $previousMonth));
            if ($confidence < self::ALERT_MIN_CONFIDENCE) {
                continue;
            }

            $type = ($variationPrev < 0.0 && $variationBaseline < 0.0) ? 'drop' : 'peak';
            $displayVariation = $type === 'drop'
                ? -$absoluteVariation
                : $absoluteVariation;

            $anomalies[] = [
                'hotelId' => $data['hotelId'],
                'hotelNom' => $data['hotelNom'],
                'nombreEtoiles' => $data['nombreEtoiles'],
                'type' => $type,
                'variation' => round($displayVariation, 1),
                'absoluteVariation' => round($absoluteVariation, 1),
                'previousCount' => $previousMonth,
                'recentCount' => $recentMonth,
                'expectedCount' => (int) round($reference),
                'confidence' => $confidence,
            ];
        }

        usort($anomalies, static function (array $a, array $b): int {
            $confidenceCmp = ((int) ($b['confidence'] ?? 0)) <=> ((int) ($a['confidence'] ?? 0));
            if ($confidenceCmp !== 0) {
                return $confidenceCmp;
            }

            return ((float) ($b['absoluteVariation'] ?? 0.0)) <=> ((float) ($a['absoluteVariation'] ?? 0.0));
        });

        return array_slice($anomalies, 0, 8);
    }

    private function computeAlertConfidence(float $absPct, int $activity, int $delta): int
    {
        $pctScore = min(1.0, $absPct / 60.0);
        $activityScore = min(1.0, $activity / 30.0);
        $deltaScore = min(1.0, $delta / 8.0);

        $confidence = ($pctScore * 0.55) + ($activityScore * 0.25) + ($deltaScore * 0.2);

        return (int) round($confidence * 100);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTopHotels(\DateTimeImmutable $today): array
    {
        $rows = $this->reservationRepository->createQueryBuilder('r')
            ->select('h.id AS hotelId, h.nom AS hotelNom, h.photoUrl AS photoUrl, h.nombreEtoiles AS nombreEtoiles, COUNT(r.id) AS reservationsCount, COALESCE(SUM(r.prixTotal), 0) AS revenueEstimate')
            ->leftJoin('r.hotel', 'h')
            ->andWhere('r.statut IN (:statuses)')
            ->andWhere('r.dateDebut <= :today')
            ->setParameter('statuses', self::RELEVANT_STATUSES)
            ->setParameter('today', $today)
            ->groupBy('h.id')
            ->addGroupBy('h.nom')
            ->addGroupBy('h.photoUrl')
            ->addGroupBy('h.nombreEtoiles')
            ->orderBy('reservationsCount', 'DESC')
            ->addOrderBy('revenueEstimate', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $row): array {
            return [
                'hotelId' => (int) ($row['hotelId'] ?? 0),
                'hotelNom' => (string) ($row['hotelNom'] ?? ''),
                'photoUrl' => is_string($row['photoUrl'] ?? null) ? trim((string) $row['photoUrl']) : null,
                'nombreEtoiles' => (int) ($row['nombreEtoiles'] ?? 0),
                'reservationsCount' => (int) ($row['reservationsCount'] ?? 0),
                'revenueEstimate' => (float) ($row['revenueEstimate'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTopRooms(\DateTimeImmutable $today): array
    {
        $rows = $this->reservationRepository->createQueryBuilder('r')
            ->select('c.id AS chambreId, c.type AS chambreType, h.id AS hotelId, h.nom AS hotelNom, COUNT(r.id) AS reservationsCount, COALESCE(AVG(r.prixTotal), 0) AS averageBasket')
            ->leftJoin('r.chambre', 'c')
            ->leftJoin('r.hotel', 'h')
            ->andWhere('r.statut IN (:statuses)')
            ->andWhere('r.dateDebut <= :today')
            ->setParameter('statuses', self::RELEVANT_STATUSES)
            ->setParameter('today', $today)
            ->groupBy('c.id')
            ->addGroupBy('c.type')
            ->addGroupBy('h.id')
            ->addGroupBy('h.nom')
            ->orderBy('reservationsCount', 'DESC')
            ->addOrderBy('averageBasket', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getArrayResult();

        return array_map(static function (array $row): array {
            return [
                'chambreId' => (int) ($row['chambreId'] ?? 0),
                'chambreType' => (string) ($row['chambreType'] ?? ''),
                'hotelId' => (int) ($row['hotelId'] ?? 0),
                'hotelNom' => (string) ($row['hotelNom'] ?? ''),
                'reservationsCount' => (int) ($row['reservationsCount'] ?? 0),
                'averageBasket' => (float) ($row['averageBasket'] ?? 0),
            ];
        }, $rows);
    }

    /**
     * @return array{labels: string[], points: int[]}
     */
    private function buildMonthlyHistory(\DateTimeImmutable $today, int $months, string $locale = 'fr'): array
    {
        $startMonth = $today
            ->modify('first day of -'.max(0, $months - 1).' months')
            ->setTime(0, 0);

        $rows = $this->reservationRepository->createQueryBuilder('r')
            ->select('r.dateDebut AS dateDebut')
            ->andWhere('r.statut IN (:statuses)')
            ->andWhere('r.dateDebut >= :startMonth')
            ->andWhere('r.dateDebut <= :today')
            ->setParameter('statuses', self::RELEVANT_STATUSES)
            ->setParameter('startMonth', $startMonth)
            ->setParameter('today', $today)
            ->orderBy('r.dateDebut', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $indexedCounts = [];
        foreach ($rows as $row) {
            $dateDebut = $row['dateDebut'] ?? null;
            if ($dateDebut instanceof \DateTimeInterface) {
                $key = $dateDebut->format('Y-m');
            } elseif (is_string($dateDebut) && $dateDebut !== '') {
                $key = (new \DateTimeImmutable($dateDebut))->format('Y-m');
            } else {
                continue;
            }

            $indexedCounts[$key] = ($indexedCounts[$key] ?? 0) + 1;
        }

        $labels = [];
        $points = [];

        for ($i = 0; $i < $months; ++$i) {
            $monthDate = $startMonth->modify('+'.$i.' months');
            $key = $monthDate->format('Y-m');
            $labels[] = $this->formatMonthYearLabel($monthDate, $locale);
            $points[] = $indexedCounts[$key] ?? 0;
        }

        return [
            'labels' => $labels,
            'points' => $points,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildGlobalYearlyProjection(\DateTimeImmutable $today, string $locale): array
    {
        $year = (int) $today->format('Y');
        $currentMonth = (int) $today->format('n');
        $start = $today->modify('first day of -23 months')->setTime(0, 0);

        $rows = $this->reservationRepository->createQueryBuilder('r')
            ->select('r.dateDebut AS dateDebut')
            ->andWhere('r.statut IN (:statuses)')
            ->andWhere('r.dateDebut >= :start')
            ->andWhere('r.dateDebut <= :today')
            ->setParameter('statuses', self::RELEVANT_STATUSES)
            ->setParameter('start', $start)
            ->setParameter('today', $today)
            ->orderBy('r.dateDebut', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $timelineKeys = $this->buildTimelineMonthKeys($start, 24);
        $timelineCounts = array_fill_keys($timelineKeys, 0);
        $actualByMonth = array_fill(1, 12, 0);

        foreach ($rows as $row) {
            $monthKey = $this->extractMonthKey($row['dateDebut'] ?? null);
            if ($monthKey !== null && array_key_exists($monthKey, $timelineCounts)) {
                $timelineCounts[$monthKey] += 1;
            }

            $dateDebut = $row['dateDebut'] ?? null;
            if (!$dateDebut instanceof \DateTimeInterface && is_string($dateDebut) && $dateDebut !== '') {
                $dateDebut = new \DateTimeImmutable($dateDebut);
            }

            if ($dateDebut instanceof \DateTimeInterface && (int) $dateDebut->format('Y') === $year) {
                $actualByMonth[(int) $dateDebut->format('n')] += 1;
            }
        }

        $series = array_values($timelineCounts);
        $seasonFactors = count($series) >= 12 ? $this->buildSeasonalFactors($series) : array_fill(0, 12, 1.0);
        $level = $this->weightedMovingAverage($series, min(6, count($series)));
        $slope = $this->linearSlope($series) * min(1.0, count($series) / 12.0);
        $maxStepChange = max(2.0, $level * 0.35);
        $slope = max(-$maxStepChange, min($slope, $maxStepChange));

        $trendMultiplier = $this->computeShortTermTrendMultiplier($series);
        $momentumMultiplier = $this->computeDemandMomentumMultiplier($series);

        $months = [];
        $actualYtd = 0;
        $predictedRemainder = 0;
        $seriesCount = count($series);

        for ($month = 1; $month <= 12; ++$month) {
            $actual = (int) ($actualByMonth[$month] ?? 0);
            if ($month <= $currentMonth) {
                $actualYtd += $actual;
            }

            $predicted = 0;
            if ($month > $currentMonth) {
                $distance = $month - $currentMonth;
                $seasonIndex = ($seriesCount + $distance - 1) % 12;
                $seasonFactor = $seasonFactors[$seasonIndex] ?? 1.0;
                $trendEstimate = max(0.0, ($level + ($slope * $distance)) * $seasonFactor);
                $predicted = (int) round(max(0.0, $trendEstimate * $trendMultiplier * $momentumMultiplier));
                $predictedRemainder += $predicted;
            }

            $months[] = [
                'month' => $month,
                'label' => $this->resolveMonthLabelShort($month, $locale),
                'actual' => $actual,
                'predicted' => $predicted,
                'total' => $month > $currentMonth ? $predicted : $actual,
                'isFuture' => $month > $currentMonth,
            ];
        }

        return [
            'year' => $year,
            'months' => $months,
            'actualYtd' => $actualYtd,
            'predictedRemainder' => $predictedRemainder,
            'projectedYearTotal' => $actualYtd + $predictedRemainder,
        ];
    }

    private function resolveMonthLabelShort(int $month, string $locale = 'fr'): string
    {
        $month = max(1, min(12, $month));
        $normalizedLocale = $this->normalizeLocale($locale);

        if ($this->canUseIntlDateFormatter()) {
            try {
                $monthDate = (new \DateTimeImmutable('2000-01-01'))->setDate(2000, $month, 1);
                $formatter = new \IntlDateFormatter($normalizedLocale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMM');
                $label = $formatter->format($monthDate);

                if (is_string($label) && trim($label) !== '') {
                    return ucfirst($label);
                }
            } catch (\Throwable) {
                // Fall back to internal locale map when intl/polyfill cannot format this locale.
            }
        }

        return $this->fallbackMonthLabel($month, $normalizedLocale);
    }

    private function monthLabelShort(int $month, string $locale = 'fr'): string
    {
        return $this->resolveMonthLabelShort($month, $locale);
    }

    private function formatMonthYearLabel(\DateTimeImmutable $date, string $locale): string
    {
        $normalizedLocale = $this->normalizeLocale($locale);
        $month = (int) $date->format('n');
        $year = $date->format('Y');

        if ($this->canUseIntlDateFormatter()) {
            try {
                $formatter = new \IntlDateFormatter($normalizedLocale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMM yyyy');
                $label = $formatter->format($date);

                if (is_string($label) && trim($label) !== '') {
                    return ucfirst($label);
                }
            } catch (\Throwable) {
                // Fall back to internal locale map when intl/polyfill cannot format this locale.
            }
        }

        return $this->fallbackMonthLabel($month, $normalizedLocale).' '.$year;
    }

    private function canUseIntlDateFormatter(): bool
    {
        return extension_loaded('intl') && class_exists(\IntlDateFormatter::class);
    }

    private function normalizeLocale(string $locale): string
    {
        $normalized = strtolower(trim($locale));
        if ($normalized === '') {
            return 'fr';
        }

        return explode('_', str_replace('-', '_', $normalized))[0] ?: 'fr';
    }

    private function fallbackMonthLabel(int $month, string $locale): string
    {
        $labels = [
            'fr' => ['janv', 'fevr', 'mars', 'avr', 'mai', 'juin', 'juil', 'aout', 'sept', 'oct', 'nov', 'dec'],
            'en' => ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'],
            'de' => ['jan', 'feb', 'mar', 'apr', 'mai', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dez'],
            'it' => ['gen', 'feb', 'mar', 'apr', 'mag', 'giu', 'lug', 'ago', 'set', 'ott', 'nov', 'dic'],
            'es' => ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'],
            'ar' => ['ينا', 'فبر', 'مار', 'أبر', 'ماي', 'يون', 'يول', 'أغس', 'سبت', 'أكت', 'نوف', 'ديس'],
        ];

        $localeLabels = $labels[$locale] ?? $labels['en'];

        return ucfirst($localeLabels[$month - 1] ?? (string) $month);
    }

    /**
     * @param int[] $historyPoints
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function forecastNextMonths(array $historyPoints, int $monthsAhead): array
    {
        $history = array_map(static fn (int $point): int => max(0, $point), $historyPoints);
        $count = count($history);
        if ($count === 0) {
            return [];
        }

        $recentWindow = array_slice($history, -min(6, $count));
        $recentAverage = count($recentWindow) > 0 ? array_sum($recentWindow) / count($recentWindow) : 0.0;
        $recentStd = $this->standardDeviation($recentWindow);

        $level = $this->weightedMovingAverage($history, min(4, $count));

        $rawSlope = $this->linearSlope($history);
        $damping = min(1.0, $count / 8.0);
        $slope = $rawSlope * $damping;

        $maxStepChange = max(2.0, $level * 0.35);
        $slope = max(-$maxStepChange, min($slope, $maxStepChange));

        $seasonalFactors = $count >= 12 ? $this->buildSeasonalFactors($history) : array_fill(0, 12, 1.0);

        $forecast = [];
        for ($i = 1; $i <= $monthsAhead; ++$i) {
            $seasonIndex = ($count + $i - 1) % 12;
            $seasonFactor = $seasonalFactors[$seasonIndex] ?? 1.0;

            $trendEstimate = max(0.0, ($level + ($slope * $i)) * $seasonFactor);
            $blended = ($trendEstimate * 0.65) + ($recentAverage * 0.35);

            if ($recentStd > 0.0) {
                $minBound = max(0.0, $recentAverage - (2.0 * $recentStd));
                $maxBound = $recentAverage + (3.0 * $recentStd) + 1.0;
            } else {
                $minBound = max(0.0, $recentAverage * 0.7);
                $maxBound = max(1.0, $recentAverage * 1.4);
            }

            $estimated = (int) round(max($minBound, min($blended, $maxBound)));

            $forecast[] = [
                'label' => 'M+'.$i,
                'value' => $estimated,
            ];
        }

        return $forecast;
    }

    /**
     * @param int[] $history
     */
    private function weightedMovingAverage(array $history, int $window): float
    {
        if ($window <= 0 || count($history) === 0) {
            return 0.0;
        }

        $slice = array_slice($history, -$window);
        $weightSum = 0.0;
        $weightedTotal = 0.0;

        foreach ($slice as $index => $value) {
            $weight = (float) ($index + 1);
            $weightedTotal += $value * $weight;
            $weightSum += $weight;
        }

        return $weightSum > 0 ? $weightedTotal / $weightSum : 0.0;
    }

    /**
     * @param int[] $history
     */
    private function linearSlope(array $history): float
    {
        $count = count($history);
        if ($count < 2) {
            return 0.0;
        }

        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumX2 = 0.0;

        foreach ($history as $index => $point) {
            $x = (float) ($index + 1);
            $y = (float) $point;
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $denominator = ($count * $sumX2) - ($sumX * $sumX);

        return $denominator !== 0.0
            ? (($count * $sumXY) - ($sumX * $sumY)) / $denominator
            : 0.0;
    }

    /**
     * @param int[] $history
     *
     * @return float[]
     */
    private function buildSeasonalFactors(array $history): array
    {
        $globalAverage = count($history) > 0 ? array_sum($history) / count($history) : 0.0;
        if ($globalAverage <= 0.0) {
            return array_fill(0, 12, 1.0);
        }

        $buckets = array_fill(0, 12, []);
        foreach ($history as $index => $value) {
            $buckets[$index % 12][] = $value;
        }

        $factors = [];
        for ($m = 0; $m < 12; ++$m) {
            $bucket = $buckets[$m];
            $monthAvg = count($bucket) > 0 ? array_sum($bucket) / count($bucket) : $globalAverage;
            $rawFactor = $monthAvg / $globalAverage;
            $factors[] = max(0.7, min($rawFactor, 1.4));
        }

        return $factors;
    }

    /**
     * @param int[] $values
     */
    private function standardDeviation(array $values): float
    {
        $count = count($values);
        if ($count < 2) {
            return 0.0;
        }

        $mean = array_sum($values) / $count;
        $variance = 0.0;

        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }

        return sqrt($variance / $count);
    }

    /**
     * @param int[] $historyPoints
     * @param int[] $forecastPoints
     *
     * @return array{score:int, label:string}
     */
    private function computeForecastConfidence(array $historyPoints, array $forecastPoints): array
    {
        $history = array_map(static fn (int $value): int => max(0, $value), $historyPoints);
        $forecast = array_map(static fn (int $value): int => max(0, $value), $forecastPoints);

        $historyCount = count($history);
        $forecastCount = count($forecast);

        if ($historyCount === 0 || $forecastCount === 0) {
            return ['score' => 0, 'label' => 'Faible'];
        }

        $historyMean = array_sum($history) / $historyCount;
        $historyStd = $this->standardDeviation($history);
        $forecastStd = $this->standardDeviation($forecast);
        $coverageRatio = min(1.0, $historyCount / 12.0);

        $historyCv = $historyMean > 0.0 ? ($historyStd / $historyMean) : 1.0;
        $forecastMean = array_sum($forecast) / $forecastCount;
        $forecastCv = $forecastMean > 0.0 ? ($forecastStd / $forecastMean) : 1.0;

        $recentTrend = $this->detectTrendDirection($history);
        $trendBonus = $recentTrend === 'stable' ? 12.0 : ($recentTrend === 'up' || $recentTrend === 'down' ? 8.0 : 0.0);

        $activityBonus = min(18.0, log10(max(1.0, array_sum($history) + 1.0)) * 6.0);
        $stabilityPenalty = min(35.0, ($historyCv * 24.0) + ($forecastCv * 20.0));

        $score = (int) round(max(0.0, min(100.0, 42.0 + ($coverageRatio * 18.0) + $trendBonus + $activityBonus - $stabilityPenalty)));

        $label = match (true) {
            $score >= 75 => 'Élevée',
            $score >= 50 => 'Moyenne',
            default => 'Faible',
        };

        return [
            'score' => $score,
            'label' => $label,
        ];
    }

    /**
     * @param int[] $historyPoints
     */
    private function detectTrendDirection(array $historyPoints): string
    {
        $count = count($historyPoints);
        if ($count < 4) {
            return 'stable';
        }

        $half = intdiv($count, 2);
        $olderHalf = array_slice($historyPoints, 0, $half);
        $newerHalf = array_slice($historyPoints, -$half);

        $oldAverage = count($olderHalf) > 0 ? array_sum($olderHalf) / count($olderHalf) : 0;
        $newAverage = count($newerHalf) > 0 ? array_sum($newerHalf) / count($newerHalf) : 0;

        if ($newAverage > $oldAverage * 1.1) {
            return 'up';
        }

        if ($newAverage < $oldAverage * 0.9) {
            return 'down';
        }

        return 'stable';
    }

    /**
     * @return array<int, array{hotelId: int, hotelNom: string, photoUrl: ?string, predictedReservations: int, sharePct: float}>
     */
    private function predictTopHotelsForNextYear(\DateTimeImmutable $today, int $predictedYearTotal): array
    {
        $start = $today->modify('first day of -11 months')->setTime(0, 0);
        $timeline = $this->buildTimelineMonthKeys($start, 12);
        $timelineIndex = array_flip($timeline);

        $rows = $this->reservationRepository->createQueryBuilder('r')
            ->select('h.id AS hotelId, h.nom AS hotelNom, h.photoUrl AS photoUrl, h.nombreEtoiles AS nombreEtoiles, r.dateDebut AS dateDebut, COUNT(r.id) AS baseCount')
            ->leftJoin('r.hotel', 'h')
            ->andWhere('r.statut IN (:statuses)')
            ->andWhere('r.dateDebut >= :start')
            ->andWhere('r.dateDebut <= :today')
            ->setParameter('statuses', self::RELEVANT_STATUSES)
            ->setParameter('start', $start)
            ->setParameter('today', $today)
            ->groupBy('h.id')
            ->addGroupBy('h.nom')
            ->addGroupBy('h.photoUrl')
            ->addGroupBy('h.nombreEtoiles')
            ->addGroupBy('r.dateDebut')
            ->orderBy('baseCount', 'DESC')
            ->getQuery()
            ->getArrayResult();

        if ($predictedYearTotal <= 0) {
            return [];
        }

        /** @var array<int, array{hotelId:int, hotelNom:string, photoUrl:?string, nombreEtoiles:int, rawCount:int, weightedScore:float, monthlyData: array<string,int>}> $hotelStats */
        $hotelStats = [];

        foreach ($rows as $row) {
            $hotelId = (int) ($row['hotelId'] ?? 0);
            if ($hotelId <= 0) {
                continue;
            }

            if (!isset($hotelStats[$hotelId])) {
                $hotelStats[$hotelId] = [
                    'hotelId' => $hotelId,
                    'hotelNom' => (string) ($row['hotelNom'] ?? ''),
                    'photoUrl' => is_string($row['photoUrl'] ?? null) ? trim((string) $row['photoUrl']) : null,
                    'nombreEtoiles' => (int) ($row['nombreEtoiles'] ?? 0),
                    'rawCount' => 0,
                    'weightedScore' => 0.0,
                    'monthlyData' => array_fill_keys($timeline, 0),
                ];
            }

            $baseCount = (int) ($row['baseCount'] ?? 0);
            $monthKey = $this->extractMonthKey($row['dateDebut'] ?? null);
            if ($monthKey === null || !array_key_exists($monthKey, $timelineIndex)) {
                continue;
            }

            $monthIndex = (int) $timelineIndex[$monthKey];
            $weight = $this->recencyWeight($monthIndex, count($timeline));

            $hotelStats[$hotelId]['rawCount'] += $baseCount;
            $hotelStats[$hotelId]['weightedScore'] += $baseCount * $weight;
            $hotelStats[$hotelId]['monthlyData'][$monthKey] += $baseCount;
        }

        if (count($hotelStats) === 0) {
            return [];
        }

        $totalScore = 0.0;
        foreach ($hotelStats as $hotelId => $stats) {
            $trendMultiplier = $this->computeShortTermTrendMultiplier(array_values($stats['monthlyData']));
            $shareMomentum = $this->computeDemandMomentumMultiplier(array_values($stats['monthlyData']));
            $adjustedScore = $stats['weightedScore'] * $trendMultiplier * $shareMomentum;
            $hotelStats[$hotelId]['weightedScore'] = $adjustedScore;
            $totalScore += $adjustedScore;
        }

        if ($totalScore <= 0.0) {
            return [];
        }

        $predictions = array_map(static function (array $stats) use ($totalScore, $predictedYearTotal): array {
            $share = $stats['weightedScore'] / $totalScore;

            return [
                'hotelId' => (int) ($stats['hotelId'] ?? 0),
                'hotelNom' => (string) ($stats['hotelNom'] ?? ''),
                'photoUrl' => $stats['photoUrl'] ?? null,
                'nombreEtoiles' => (int) ($stats['nombreEtoiles'] ?? 0),
                'predictedReservations' => (int) round($predictedYearTotal * $share),
                'sharePct' => round($share * 100, 1),
            ];
        }, array_values($hotelStats));

        usort($predictions, static fn (array $a, array $b): int => $b['predictedReservations'] <=> $a['predictedReservations']);

        return array_slice($predictions, 0, 6);
    }

    /**
     * @return array<int, array{roomType: string, predictedReservations: int, sharePct: float}>
     */
    private function predictTopRoomTypesForNextYear(\DateTimeImmutable $today, int $predictedYearTotal): array
    {
        $start = $today->modify('first day of -11 months')->setTime(0, 0);
        $timeline = $this->buildTimelineMonthKeys($start, 12);
        $timelineIndex = array_flip($timeline);

        $rows = $this->reservationRepository->createQueryBuilder('r')
            ->select('c.type AS roomType, r.dateDebut AS dateDebut, COUNT(r.id) AS baseCount')
            ->leftJoin('r.chambre', 'c')
            ->andWhere('r.statut IN (:statuses)')
            ->andWhere('r.dateDebut >= :start')
            ->andWhere('r.dateDebut <= :today')
            ->setParameter('statuses', self::RELEVANT_STATUSES)
            ->setParameter('start', $start)
            ->setParameter('today', $today)
            ->groupBy('c.type')
            ->addGroupBy('r.dateDebut')
            ->orderBy('baseCount', 'DESC')
            ->getQuery()
            ->getArrayResult();

        if ($predictedYearTotal <= 0) {
            return [];
        }

        /** @var array<string, array{roomType:string, weightedScore:float, monthlyData: array<string,int>}> $roomStats */
        $roomStats = [];
        foreach ($rows as $row) {
            $roomType = trim((string) ($row['roomType'] ?? '-'));
            if ($roomType === '') {
                $roomType = '-';
            }

            if (!isset($roomStats[$roomType])) {
                $roomStats[$roomType] = [
                    'roomType' => $roomType,
                    'weightedScore' => 0.0,
                    'monthlyData' => array_fill_keys($timeline, 0),
                ];
            }

            $baseCount = (int) ($row['baseCount'] ?? 0);
            $monthKey = $this->extractMonthKey($row['dateDebut'] ?? null);
            if ($monthKey === null || !array_key_exists($monthKey, $timelineIndex)) {
                continue;
            }

            $monthIndex = (int) $timelineIndex[$monthKey];
            $weight = $this->recencyWeight($monthIndex, count($timeline));

            $roomStats[$roomType]['weightedScore'] += $baseCount * $weight;
            $roomStats[$roomType]['monthlyData'][$monthKey] += $baseCount;
        }

        if (count($roomStats) === 0) {
            return [];
        }

        $totalScore = 0.0;
        foreach ($roomStats as $roomType => $stats) {
            $trendMultiplier = $this->computeShortTermTrendMultiplier(array_values($stats['monthlyData']));
            $shareMomentum = $this->computeDemandMomentumMultiplier(array_values($stats['monthlyData']));
            $adjustedScore = $stats['weightedScore'] * $trendMultiplier * $shareMomentum;
            $roomStats[$roomType]['weightedScore'] = $adjustedScore;
            $totalScore += $adjustedScore;
        }

        if ($totalScore <= 0.0) {
            return [];
        }

        $predictions = array_map(static function (array $stats) use ($totalScore, $predictedYearTotal): array {
            $share = $stats['weightedScore'] / $totalScore;

            return [
                'roomType' => (string) ($stats['roomType'] ?? '-'),
                'predictedReservations' => (int) round($predictedYearTotal * $share),
                'sharePct' => round($share * 100, 1),
            ];
        }, array_values($roomStats));

        usort($predictions, static fn (array $a, array $b): int => $b['predictedReservations'] <=> $a['predictedReservations']);

        return array_slice($predictions, 0, 8);
    }

    /**
     * @return string[]
     */
    private function buildTimelineMonthKeys(\DateTimeImmutable $start, int $months): array
    {
        $keys = [];
        for ($i = 0; $i < $months; ++$i) {
            $keys[] = $start->modify('+'.$i.' months')->format('Y-m');
        }

        return $keys;
    }

    private function extractMonthKey(mixed $dateValue): ?string
    {
        if ($dateValue instanceof \DateTimeInterface) {
            return $dateValue->format('Y-m');
        }

        if (is_string($dateValue) && trim($dateValue) !== '') {
            return (new \DateTimeImmutable($dateValue))->format('Y-m');
        }

        return null;
    }

    private function recencyWeight(int $monthIndex, int $totalMonths): float
    {
        if ($totalMonths <= 1) {
            return 1.0;
        }

        $progress = $monthIndex / ($totalMonths - 1);

        return 0.7 + (0.9 * $progress);
    }

    /**
     * @param int[] $monthlySeries
     */
    private function computeShortTermTrendMultiplier(array $monthlySeries): float
    {
        $recent = (int) array_sum(array_slice($monthlySeries, -3));
        $previous = (int) array_sum(array_slice($monthlySeries, -6, 3));

        if ($previous <= 0) {
            return $recent > 0 ? 1.12 : 1.0;
        }

        $ratio = ($recent - $previous) / $previous;
        $multiplier = 1.0 + ($ratio * 0.25);

        return max(0.85, min($multiplier, 1.25));
    }

    /**
     * @param int[] $monthlySeries
     */
    private function computeDemandMomentumMultiplier(array $monthlySeries): float
    {
        $total = (int) array_sum($monthlySeries);
        if ($total <= 0) {
            return 1.0;
        }

        $recentThree = (int) array_sum(array_slice($monthlySeries, -3));
        $olderNine = max(0, $total - $recentThree);

        if ($olderNine <= 0) {
            return $recentThree > 0 ? 1.08 : 1.0;
        }

        $recentShare = $recentThree / $total;
        $historicalShare = $olderNine / $total;

        $momentum = $recentShare - $historicalShare;
        $multiplier = 1.0 + ($momentum * 0.9);

        return max(0.8, min($multiplier, 1.22));
    }

}
