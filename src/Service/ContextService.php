<?php

namespace App\Service;

class ContextService
{
    /**
     * Detects current time moment.
     */
    public function getCurrentMoment(): string
    {
        $hour = (int) date('H');
        
        if ($hour >= 5 && $hour < 12) return 'morning';
        if ($hour >= 12 && $hour < 18) return 'afternoon';
        if ($hour >= 18 && $hour < 22) return 'evening';
        return 'night';
    }

    /**
     * Detects if it's weekend.
     */
    public function isWeekend(): bool
    {
        return date('N') >= 6;
    }

    /**
     * Mocks weather data for a city.
     * In a real app, this would call a weather API.
     */
    public function getWeather(string $city = ''): string
    {
        // Simple mock: sunny if weekend, rainy if weekday morning, etc.
        // Or just random for diversity in demo.
        $hours = (int) date('H');
        if ($hours > 8 && $hours < 11) return 'rainy';
        return 'sunny';
    }

    /**
     * Check if an activity is currently open.
     */
    public function isOpen($openingTime, $closingTime): bool
    {
        if (!$openingTime || !$closingTime) return true; // Always open if not specified

        $now = new \DateTime();
        $open = \DateTime::createFromFormat('H:i:s', $openingTime->format('H:i:s'));
        $close = \DateTime::createFromFormat('H:i:s', $closingTime->format('H:i:s'));

        // Handle closing time after midnight
        if ($close < $open) {
            if ($now >= $open || $now <= $close) return true;
        }

        return $now >= $open && $now <= $close;
    }
}
