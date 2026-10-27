<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket 2 — Tahap 1: pemisahan catatan quotation (aditif).
 *
 * proposals.notes = catatan yang ikut tercetak pada preview pelanggan dan
 *   disalin ke deskripsi project saat konversi (makna lama TIDAK diubah).
 * proposals.internal_notes = catatan internal baru — TIDAK tampil di preview
 *   pelanggan. Data lama tidak dimigrasi (makna notes historis belum pasti).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->text('internal_notes')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn('internal_notes');
        });
    }
};
