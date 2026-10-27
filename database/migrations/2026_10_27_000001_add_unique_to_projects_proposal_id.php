<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket 1 — Tahap 1: proteksi konversi quotation di level database.
 *
 * projects.proposal_id dibuat unique agar satu quotation tidak bisa
 * menghasilkan dua project (lapisan kedua setelah guard di
 * ProposalController::convertToProject). Kolom tetap nullable —
 * project lama/tanpa proposal tidak terpengaruh (PostgreSQL & SQLite
 * mengizinkan banyak NULL pada unique index).
 *
 * Pra-syarat (dicek sebelum migration dibuat, 2026-10-27):
 * - DB produksi (pgsql): 0 proposal_id duplikat, 0 project dengan proposal_id.
 * - Tidak ada data yang perlu dihapus/diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unique('proposal_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['proposal_id']);
        });
    }
};
