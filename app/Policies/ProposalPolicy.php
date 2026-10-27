<?php

namespace App\Policies;

use App\Models\Proposal;
use App\Models\User;

/**
 * Hak akses informasi finansial internal quotation (harga modal, laba kotor,
 * gross margin) — sesuai keputusan Paket 2 Tahap 1 Renaissance:
 *
 * - Super-admin: akses penuh (via Gate::before).
 * - Admin/Purchasing (manage-admin): lihat & ubah.
 * - Sales/Inside Sales pengelola quotation (manage-sales / manage-inside-sales /
 *   manage-sales-leads): lihat untuk menyusun penawaran.
 * - Management (manage-sales-leads): baca.
 * - CEO: baca (role `ceo` = semua view-*, read-only sesuai matriks).
 * - Marketing, Prakerin, teknisi, pelanggan: TIDAK boleh lihat.
 *
 * CEO dicek lewat role aktual (bukan `view-sales`, karena `view-sales` juga
 * dipegang lead-technician yang tidak boleh melihat harga modal).
 */
class ProposalPolicy
{
    public function viewCost(User $user, ?Proposal $proposal = null): bool
    {
        return $user->can('manage-admin')
            || $user->can('manage-sales')
            || $user->can('manage-inside-sales')
            || $user->can('manage-sales-leads')
            || $user->hasRole('ceo');
    }

    /**
     * Mengubah harga modal (item quotation & produk) hanya Admin/Purchasing
     * (manage-admin) — Super Admin tetap lewat Gate::before.
     */
    public function updateCost(User $user, ?Proposal $proposal = null): bool
    {
        return $user->can('manage-admin');
    }
}
