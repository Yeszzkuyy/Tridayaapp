<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instalasis', function (Blueprint $table) {
            // File laporan instalasi (PDF/DOC) di disk privat — nama asli upload
            // disimpan untuk unduhan. Kolom text installation_report/notes lama
            // tidak di-drop agar data existing tetap aman.
            $table->string('report_file')->nullable()->after('installation_report');
            $table->string('report_file_name')->nullable()->after('report_file');
        });
    }

    public function down(): void
    {
        Schema::table('instalasis', function (Blueprint $table) {
            $table->dropColumn(['report_file', 'report_file_name']);
        });
    }
};
