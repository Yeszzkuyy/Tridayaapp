<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumen hasil modul (survey/sizing/instalasi) ikut tercatat di
     * project_documents. Untuk file lama yang didaftarkan lewat perintah
     * sinkronisasi, pengunggah aslinya tidak diketahui — jadi kolom ini
     * boleh kosong (sebelumnya wajib terisi).
     */
    public function up(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->foreignId('uploaded_by')->nullable(false)->change();
        });
    }
};
