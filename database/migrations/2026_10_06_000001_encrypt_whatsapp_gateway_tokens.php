<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token gateway terenkripsi at-rest (base64) lebih panjang dari
     * varchar(255), jadi kolom ikut diubah ke text + data dienkripsi.
     */
    public function up(): void
    {
        // Tanpa doctrine/dbal: ubah tipe via SQL mentah per driver.
        // SQLite (test) tidak menegakkan panjang varchar → no-op.
        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE whatsapp_accounts ALTER COLUMN gateway_token TYPE TEXT'),
            'mysql' => DB::statement('ALTER TABLE whatsapp_accounts MODIFY gateway_token TEXT NULL'),
            default => null,
        };

        DB::table('whatsapp_accounts')
            ->whereNotNull('gateway_token')
            ->orderBy('id')
            ->each(function ($row) {
                // Samakan dengan cast model 'encrypted': tanpa serialisasi.
                try {
                    decrypt($row->gateway_token, false);

                    return; // sudah terenkripsi format cast
                } catch (\Throwable) {
                    DB::table('whatsapp_accounts')
                        ->where('id', $row->id)
                        ->update(['gateway_token' => encrypt($row->gateway_token, false)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('whatsapp_accounts')
            ->whereNotNull('gateway_token')
            ->orderBy('id')
            ->each(function ($row) {
                try {
                    $plain = decrypt($row->gateway_token, false);
                    DB::table('whatsapp_accounts')
                        ->where('id', $row->id)
                        ->update(['gateway_token' => $plain]);
                } catch (\Throwable) {
                    // bukan hasil encrypt(): biarkan apa adanya
                }
            });

        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE whatsapp_accounts ALTER COLUMN gateway_token TYPE VARCHAR(255)'),
            'mysql' => DB::statement('ALTER TABLE whatsapp_accounts MODIFY gateway_token VARCHAR(255) NULL'),
            default => null,
        };
    }
};
