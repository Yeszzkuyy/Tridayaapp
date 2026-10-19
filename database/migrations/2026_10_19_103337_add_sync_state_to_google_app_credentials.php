<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_app_credentials', function (Blueprint $table) {
            // Status sinkronisasi terakhir (tanpa rahasia) untuk badge koneksi.
            // Semua kolom bersifat tambahan (additive) — tidak menghapus/mengubah data lama.
            $table->timestamp('last_sync_at')->nullable()->after('calendar_id');
            $table->string('last_sync_summary', 255)->nullable()->after('last_sync_at');
            $table->text('last_sync_error')->nullable()->after('last_sync_summary');
            // auth | network | sync — untuk membedakan kegagalan otorisasi vs jaringan vs sinkron.
            $table->string('last_sync_error_kind', 20)->nullable()->after('last_sync_error');
        });
    }

    public function down(): void
    {
        Schema::table('google_app_credentials', function (Blueprint $table) {
            $table->dropColumn(['last_sync_at', 'last_sync_summary', 'last_sync_error', 'last_sync_error_kind']);
        });
    }
};
