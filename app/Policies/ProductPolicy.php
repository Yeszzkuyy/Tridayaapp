<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Hak akses harga produk.
 *
 * - manageCost: harga modal (cost_price) untuk Admin/Purchasing dan Estimator.
 * - manageSellingPrice: harga jual default (default_price) hanya Admin/Purchasing.
 *   Sales/Inside Sales tetap bisa mengubah harga jual pada quotation.
 * Super Admin tetap lewat Gate::before.
 */
class ProductPolicy
{
    public function viewCost(User $user, ?Product $product = null): bool
    {
        return $user->can('manage-admin')
            || $user->can('manage-sales')
            || $user->can('manage-inside-sales')
            || $user->can('manage-price-requests')
            || $user->can('manage-sales-leads')
            || $user->hasRole('ceo');
    }

    public function manageCost(User $user, ?Product $product = null): bool
    {
        return $user->can('manage-admin') || $user->can('manage-price-requests');
    }

    public function manageSellingPrice(User $user, ?Product $product = null): bool
    {
        return $user->can('manage-admin');
    }
}
