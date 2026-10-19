<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus kolom api_key dari google_app_credentials.
 *
 * API key sudah tidak dipakai — semua operasi Google Calendar kini via OAuth.
 * Field UI + seluruh referensi kode sudah dihapus; kolom ini tinggal peninggalan.
 *
 * PERHATIAN (destruktif): dropColumn menghapus data API key yang tersimpan dan
 * TIDAK bisa dipulihkan oleh down(). down() hanya mengembalikan kolom kosong
 * (text nullable, bentuk terakhir sebelum dihapus). Ambil backup sebelum
 * menjalankan migrasi ini di produksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_app_credentials', function (Blueprint $table) {
            $table->dropColumn('api_key');
        });
    }

    public function down(): void
    {
        Schema::table('google_app_credentials', function (Blueprint $table) {
            // Kembalikan kolom (tanpa data lama) sebagai text nullable.
            $table->text('api_key')->nullable();
        });
    }
};
