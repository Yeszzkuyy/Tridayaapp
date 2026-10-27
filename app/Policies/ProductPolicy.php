<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

/**
 * Hak akses harga modal produk (products.cost_price).
 * Hanya Admin/Purchasing (manage-admin) yang boleh mengubah;
 * Super Admin tetap lewat Gate::before. Lihat: ProposalPolicy::viewCost.
 */
class ProductPolicy
{
    public function manageCost(User $user, ?Product $product = null): bool
    {
        return $user->can('manage-admin');
    }
}
