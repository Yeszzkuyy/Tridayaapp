<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inisial Sales untuk nomor quotation (format PT/urut/inisial/tahun).
     * Nullable → diisi otomatis dari nama bila kosong; Admin boleh koreksi.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('initials', 10)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('initials');
        });
    }
};
