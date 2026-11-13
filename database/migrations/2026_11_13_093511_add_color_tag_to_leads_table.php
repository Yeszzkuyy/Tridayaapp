<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Penanda warna per lead (merah/kuning/hijau) untuk membedakan cepat. */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('color_tag', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('color_tag');
        });
    }
};
