<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Siapa yang memindahkan dokumen ke Trash. Dipakai untuk menentukan
     * dokumen mana yang boleh dilihat/dipulihkan di "Trash Saya" — selain
     * pemilik customer-nya.
     */
    public function up(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->dropColumn('deleted_by');
        });
    }
};
