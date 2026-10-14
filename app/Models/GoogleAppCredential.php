<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kredensial aplikasi Google Calendar (client ID/secret + API key).
 * Satu baris global — di-manage super-admin / lead-technician via
 * modal di halaman jadwal teknisi. Rahasia disimpan ter-encrypt.
 */
class GoogleAppCredential extends Model
{
    protected $table = 'google_app_credentials';

    protected $fillable = [
        'client_id',
        'client_secret',
        'api_key',
        'calendar_id',
    ];

    protected $casts = [
        'client_secret' => 'encrypted',
        'api_key' => 'encrypted',
    ];

    /**
     * Ambil baris kredensial (atau model kosong bila belum diisi).
     */
    public static function current(): self
    {
        return static::query()->first() ?? new static();
    }
}
