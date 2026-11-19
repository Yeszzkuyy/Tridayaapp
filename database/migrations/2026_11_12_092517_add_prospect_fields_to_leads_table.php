<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kelengkapan prospek dari wawancara Sales (Adi Santosa):
     * - project_name      : nama proyek (wajib untuk lead baru).
     * - need_type          : jenis kebutuhan (CCTV, PABX, dst).
     * - procurement_status : pemasangan baru vs penggantian (replacement).
     *
     * Semua nullable supaya data lama tetap valid dan tidak perlu migrasi data.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('project_name', 255)->nullable()->after('kebutuhan');
            $table->string('need_type', 40)->nullable()->after('project_name');
            $table->string('procurement_status', 20)->nullable()->after('need_type');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['project_name', 'need_type', 'procurement_status']);
        });
    }
};
