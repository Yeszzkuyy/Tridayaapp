<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google-calendar' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'calendar_id' => env('GOOGLE_CALENDAR_ID'),
    ],

    // Google Maps / Places (autocomplete lokasi jadwal sales). Dipakai di sisi
    // browser — batasi key-nya di Google Cloud (HTTP referrer + Maps/Places API).
    'google-maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY', env('GOOGLE_API_KEY')),
        'region' => env('GOOGLE_MAPS_REGION', 'id'),
    ],

    /*
    | URL template Google Docs untuk laporan survey. Diisi copy-link template
    | Google Docs milik tim (configurable, bukan template id palsu).
    | Bila kosong, tombol "Create Report" memakai halaman buat dokumen Google Docs.
    */
    'survey-report-template' => env('SURVEY_REPORT_TEMPLATE_URL', 'https://docs.google.com/document/create'),

    /*
    | URL template Google Drive untuk dokumen BAST, per perusahaan
    | (MGK / NTI / TPS / WANI). Diisi copy-link template (format .../copy)
    | supaya pengguna membuat salinan sendiri tanpa mengubah template asli.
    | Tautan dibuka apa adanya di tab baru — tanpa Google Drive API.
    | Entry yang kosong tidak ditampilkan di tombol BAST.
    */
    'bast-templates' => [
        'MGK' => env('BAST_TEMPLATE_MGK_URL'),
        'NTI' => env('BAST_TEMPLATE_NTI_URL'),
        'TPS' => env('BAST_TEMPLATE_TPS_URL'),
        'WANI' => env('BAST_TEMPLATE_WANI_URL'),
    ],

];
