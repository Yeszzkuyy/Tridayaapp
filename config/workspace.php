<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Multi-Tab Workspace (Keep-Alive di Top Navbar)
    |--------------------------------------------------------------------------
    |
    | Mengganti pill lokasi di navbar dengan deretan tab yang tetap hidup.
    | Bisa dimatikan seketika lewat WORKSPACE_TABS_ENABLED=false tanpa
    | mengubah kode lain.
    |
    */
    'enabled' => (bool) env('WORKSPACE_TABS_ENABLED', true),

    /*
    | Batas jumlah tab aktif (termasuk Dashboard yang dipin) agar memori
    | browser tetap ringan.
    */
    'max_tabs_desktop' => (int) env('WORKSPACE_MAX_TABS_DESKTOP', 8),
    'max_tabs_mobile' => (int) env('WORKSPACE_MAX_TABS_MOBILE', 6),
];
