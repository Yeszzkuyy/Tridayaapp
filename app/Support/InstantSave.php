<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Penanda modul yang memakai simpan cepat (AJAX + toast langsung).
 *
 * Dipakai bersama oleh layout (atribut data-instant-save di <html>) dan
 * middleware InstantSaveResponse supaya daftar modulnya tidak berbeda.
 */
final class InstantSave
{
    /** Route yang memakai simpan cepat. */
    public const ROUTES = [
        'estimator.*',
        'products.*',
        'sales.price-requests.*',
    ];

    public static function enabledFor(?Request $request): bool
    {
        if (! $request || ! $request->route()) {
            return false;
        }

        return $request->routeIs(...self::ROUTES);
    }
}
