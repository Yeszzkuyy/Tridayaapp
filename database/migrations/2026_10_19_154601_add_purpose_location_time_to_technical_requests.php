<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom baru form Request: Purpose (pengganti Problem Description),
     * Location (alamat / link Maps), dan Target Time.
     * Kolom lama (requirement/problem_description/scope) sengaja dibiarkan
     * agar request lama tetap utuh.
     */
    public function up(): void
    {
        Schema::table('technical_requests', function (Blueprint $table) {
            $table->string('location', 255)->nullable()->after('title');
            $table->text('purpose')->nullable()->after('location');
            $table->time('target_time')->nullable()->after('target_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('technical_requests', function (Blueprint $table) {
            $table->dropColumn(['location', 'purpose', 'target_time']);
        });
    }
};
