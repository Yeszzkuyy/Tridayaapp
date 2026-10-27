<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Kredensial aplikasi Google Calendar (client ID/secret).
 * Satu baris global — di-manage super-admin / lead-technician via
 * modal di halaman jadwal teknisi. Rahasia disimpan ter-encrypt.
 */
class GoogleAppCredential extends Model
{
    protected $table = 'google_app_credentials';

    protected $fillable = [
        'client_id',
        'client_secret',
        'calendar_id',
    ];

    protected $casts = [
        'client_secret' => 'encrypted',
    ];

    /**
     * Ambil baris kredensial (atau model kosong bila belum diisi).
     */
    public static function current(): self
    {
        return static::query()->first() ?? new static();
    }

    /**
     * Cek ada/tidaknya secret tersimpan TANPA men-decrypt (aman dari
     * kegagalan dekripsi). Dipakai view untuk placeholder — jangan
     * menampilkan nilai rahasia.
     */
    public function hasSecret(): bool
    {
        return ! empty($this->getAttributes()['client_secret']);
    }
}
