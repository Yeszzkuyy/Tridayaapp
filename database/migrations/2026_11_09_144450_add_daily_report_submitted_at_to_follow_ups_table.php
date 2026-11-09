<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('follow_ups', 'daily_report_submitted_at')) {
            return;
        }

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->timestamp('daily_report_submitted_at')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('follow_ups', 'daily_report_submitted_at')) {
            return;
        }

        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropColumn('daily_report_submitted_at');
        });
    }
};
