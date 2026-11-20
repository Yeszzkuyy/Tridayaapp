<?php

namespace App\Support;

/**
 * Shared cost-price calculations for the Pricelist update flows.
 */
final class Pricing
{
    public static function vatRate(): float
    {
        return max(0, min(100, (float) config('pricing.vat_rate', 12)));
    }

    public static function netCost(float $amount, bool $includesTax = false, ?float $vatRate = null): float
    {
        $rate = $vatRate ?? self::vatRate();

        if (! $includesTax || $rate <= 0) {
            return round($amount, 2);
        }

        return round($amount / (1 + ($rate / 100)), 2);
    }
}
