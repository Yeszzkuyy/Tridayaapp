<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Gateway
    |--------------------------------------------------------------------------
    | Tiap akun punya gateway sendiri, ditandai kolom gateway_type:
    |   - 'green_api' (default): Green API. Kredensial per akun di tabel
    |     whatsapp_accounts (gateway_instance = IdInstance,
    |     gateway_token = ApiTokenInstance).
    |   - 'meta': WhatsApp Business Cloud API. Kredensial per akun
    |     (gateway_instance = Phone Number ID, gateway_token = System User
    |     Access Token). Support banyak akun Meta — webhook di-route lewat
    |     metadata.phone_number_id payload.
    */

    'base_url' => env('GREEN_API_BASE_URL', 'https://api.green-api.com'),

    /*
    | Bot WhatsApp otomatis (lihat App\Services\WhatsappBot).
    | max_turns: batas balasan bot per percakapan sebelum handoff ke manusia.
    | reminder_minutes: jeda minimal notifikasi pengingat untuk percakapan mode human.
    */
    'bot' => [
        'max_turns' => (int) env('WA_BOT_MAX_TURNS', 5),
        'reminder_minutes' => (int) env('WA_BOT_REMINDER_MINUTES', 30),
    ],

    /*
    | Meta WhatsApp Business Cloud API.
    | verify_token: string bebas yang KAMU karang sendiri (bukan token Meta),
    | harus sama persis dengan yang dimasukkan di Dashboard Meta →
    | WhatsApp → Configuration → Webhooks → Verify Token. Dipakai untuk
    | validasi pendaftaran webhook (hub.challenge).
    */
    'meta' => [
        'api_version' => 'v19.0',
        'graph_base_url' => 'https://graph.facebook.com',
        'verify_token' => env('META_WA_VERIFY_TOKEN', ''),
        // App Secret Meta (Dashboard Meta → Pengaturan Aplikasi) untuk
        // verifikasi HMAC X-Hub-Signature-256 pada POST webhook.
        // Kosong = verifikasi dilewati (dev/test); isi di prod.
        'app_secret' => env('META_WA_APP_SECRET', ''),
    ],
];