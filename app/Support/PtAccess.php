<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\User;

/**
 * Akses tulis per PT via role sales-*: sales-mgk -> MGK.
 * Satu user boleh pegang banyak PT. Tanpa role PT = tanpa batas (grandfather).
 * Management (hub) tetap tanpa batas walau punya role sales-*.
 */
class PtAccess
{
    /**
     * User yang bebas dari batas PT: super-admin, management (hub), dan
     * pengelola lead lintas divisi. Role sales-* pada mereka hanya penanda
     * identitas, tidak mengunci akses.
     */
    public static function isUnrestricted(User $user): bool
    {
        return $user->hasRole('super-admin')
            || $user->hasRole('management')
            || $user->can('manage-sales-leads');
    }

    /**
     * Daftar PT dari role sales-* yang dipegang user, mis. sales-mgk -> MGK.
     * User unrestricted selalu mengembalikan [] (tanpa batas).
     */
    public static function userPts(User $user): array
    {
        if (self::isUnrestricted($user)) {
            return [];
        }

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
     * Boleh tulis data PT ini? User unrestricted / tanpa role PT / PT kosong = ya.
     */
    public static function canWritePt(User $user, ?string $ptGroup): bool
    {
        if (self::isUnrestricted($user)) {
            return true;
        }

        $pts = self::userPts($user);
        if ($pts === [] || !$ptGroup) {
            return true;
        }

        return in_array(strtoupper($ptGroup), $pts, true);
    }
}
