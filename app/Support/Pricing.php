<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Rumus harga yang dipakai lintas menu (Estimator, Inside Sales, Quotation).
 *
 * Aturan bisnis:
 * - Margin adalah gross profit margin dari harga jual SEBELUM PPN.
 * - Harga modal yang dipakai menghitung margin selalu harga sebelum PPN.
 * - Harga jual = (modal bersih x qty + diskon) / (qty x (1 - margin)).
 */
class Pricing
{
    public static function netCost(float $amount, bool $includesTax = false, ?float $vatRate = null): float
    {
        $rate = $vatRate ?? AppSetting::vatRate();

        if (! $includesTax || $rate <= 0) {
            return round($amount, 2);
        }

        return round($amount / (1 + ($rate / 100)), 2);
    }

    public static function sellingPrice(
        float $netCost,
        float $marginPercent,
        float $quantity = 1,
        float $discount = 0,
    ): float {
        $margin = max(0, min(99.99, $marginPercent));
        $quantity = max(0.0001, $quantity);
        $discount = max(0, $discount);

        $target = ($netCost / (1 - ($margin / 100))) + ($discount / $quantity);

        // Bulatkan ke atas ke rupiah penuh agar margin tidak jatuh di bawah target.
        return (float) ceil($target);
    }

    public static function marginAmount(
        float $unitPrice,
        float $netCost,
        float $quantity = 1,
        float $discount = 0,
    ): float {
        $quantity = max(0, $quantity);

        return round((($unitPrice * $quantity) - max(0, $discount)) - ($netCost * $quantity), 2);
    }

    public static function marginPercent(
        float $unitPrice,
        float $netCost,
        float $quantity = 1,
        float $discount = 0,
    ): ?float {
        $quantity = max(0, $quantity);
        $netSales = ($unitPrice * $quantity) - max(0, $discount);

        if ($quantity <= 0 || $netSales <= 0) {
            return null;
        }

        return round((($netSales - ($netCost * $quantity)) / $netSales) * 100, 2);
    }
}
