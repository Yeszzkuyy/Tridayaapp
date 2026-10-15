<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Koreksi migrasi 2026_10_06_000001: token sempat dienkripsi dengan
     * serialisasi (encrypt default), sementara cast model 'encrypted'
     * memakai tanpa serialisasi — gateway menerima token berlapis
     * `s:...;` dan ditolak provider. Normalisasi ke format cast.
     * Idempotent: aman dijalankan ulang & untuk semua state (plaintext,
     * envelope serialisasi, maupun sudah benar).
     */
    public function up(): void
    {
        DB::table('whatsapp_accounts')
            ->whereNotNull('gateway_token')
            ->orderBy('id')
            ->each(function ($row) {
                $plain = $this->normalize($row->gateway_token);

                if ($plain === null) {
                    return;
                }

                DB::table('whatsapp_accounts')
                    ->where('id', $row->id)
                    ->update(['gateway_token' => encrypt($plain, false)]);
            });
    }

    public function down(): void
    {
        // Tidak ada rollback data yang aman; format baru adalah yang benar.
    }

    /**
     * @return string|null plaintext bila perlu ditulis ulang, null bila sudah benar.
     */
    private function normalize(mixed $raw): ?string
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            $asCast = decrypt($raw, false);
        } catch (\Throwable) {
            return $raw; // plaintext lama → enkripsi langsung
        }

        if (! is_string($asCast)) {
            return null;
        }

        if (! preg_match('/^(s:\d+:".*";|a:\d+:\{|i:-?\d+;|b:[01];|N;)$/s', $asCast)) {
            return null; // sudah format cast → biarkan
        }

        try {
            $plain = decrypt($raw, true);
        } catch (\Throwable) {
            return null;
        }

        return is_string($plain) ? $plain : null;
    }
};
