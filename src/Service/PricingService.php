<?php

namespace App\Service;

use App\Entity\ActiviteSession;
use App\Entity\Personne;
use App\Entity\Coupon;

class PricingService
{
    /**
     * Calculates the final price for a booking.
     * 
     * @return array Returns ['finalPrice' => float, 'breakdown' => array]
     */
    public function calculatePrice(ActiviteSession $session, ?Personne $user, ?Coupon $coupon = null): array
    {
        $basePrice = (float) $session->getActivite()->getPrix();
        $currentPrice = $basePrice;
        $breakdown = [];

        // 1. User Type Discount
        if ($user) {
            $type = $user->getCustomerType();
            if ($type === 'etudiant') {
                $discount = $currentPrice * 0.20;
                $currentPrice -= $discount;
                $breakdown[] = 'Réduction Étudiant (-20%): -' . number_format($discount, 2) . ' DT';
            } elseif ($type === 'premium' || ($user->getSubscriptionExpiresAt() && $user->getSubscriptionExpiresAt() > new \DateTime())) {
                $discount = $currentPrice * 0.50;
                $currentPrice -= $discount;
                $breakdown[] = 'Réduction Premium (-50%): -' . number_format($discount, 2) . ' DT';
            }
        }

        // 2. Timing Rules
        $sessionDate = $session->getDate();
        $sessionTime = $session->getHeure();
        $sessionDateTime = new \DateTime($sessionDate->format('Y-m-d') . ' ' . $sessionTime->format('H:i:s'));
        $now = new \DateTime();
        
        $diff = $now->diff($sessionDateTime);
        $hoursRemaining = ($diff->days * 24) + $diff->h;

        if ($hoursRemaining > 168) { // > 7 days
            $discount = $basePrice * 0.15;
            $currentPrice -= $discount;
            $breakdown[] = 'Offre Early Bird (-15%): -' . number_format($discount, 2) . ' DT';
        } elseif ($hoursRemaining < 6 && !$diff->invert) { // < 6 hours
            $extra = $basePrice * 0.20;
            $currentPrice += $extra;
            $breakdown[] = 'Frais Last Minute (+20%): +' . number_format($extra, 2) . ' DT';
        }

        // 3. Coupon Code
        if ($coupon && $coupon->isValid()) {
            if ($coupon->getType() === 'percent') {
                $discount = $currentPrice * ((float)$coupon->getDiscountValue() / 100);
            } else {
                $discount = (float) $coupon->getDiscountValue();
            }
            $currentPrice = max(0, $currentPrice - $discount);
            $breakdown[] = 'Coupon "' . $coupon->getCode() . '": -' . number_format($discount, 2) . ' DT';
        }

        return [
            'finalPrice' => round($currentPrice, 2),
            'breakdown' => $breakdown,
            'basePrice' => $basePrice
        ];
    }
}
