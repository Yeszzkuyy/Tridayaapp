<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Meeting (dipisah dari Follow Up): penanda lokasi meeting.
 * Nilai: office | field | online. Nullable untuk data lama/laporan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->string('meeting_mode', 20)->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropColumn('meeting_mode');
        });
    }
};
