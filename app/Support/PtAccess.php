<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\User;

/**
 * Akses tulis per PT via role sales-*: sales-mgk -> MGK.
 * Satu user boleh pegang banyak PT. Tanpa role PT = tanpa batas (grandfather).
 */
class PtAccess
{
    /**
     * Daftar PT dari role sales-* yang dipegang user, mis. sales-mgk -> MGK.
     */
    public static function userPts(User $user): array
    {
        $pts = [];
        foreach ($user->roles()->pluck('name') as $role) {
            if (preg_match('/^sales-([a-z]+)$/i', (string) $role, $m)) {
                $pt = strtoupper($m[1]);
                if (in_array($pt, Lead::PT_GROUPS, true)) {
                    $pts[] = $pt;
                }
            }
        }

        return array_values(array_unique($pts));
    }

    /**
     * Sales-like: role sales atau salah satu sales-* (mis. sales-mgk).
     * Dipakai untuk scoping own-lead yang sama seperti sales polos.
     */
    public static function isSalesLike(User $user): bool
    {
        return $user->hasRole('sales') || self::userPts($user) !== [];
    }

    /**
     * Boleh tulis data PT ini? super-admin / tanpa role PT / PT kosong = ya.
     */
    public static function canWritePt(User $user, ?string $ptGroup): bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }

        $pts = self::userPts($user);
        if ($pts === [] || !$ptGroup) {
            return true;
        }

        return in_array(strtoupper($ptGroup), $pts, true);
    }
}
