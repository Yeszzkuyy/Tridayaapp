<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status follow-up dipisah dari status pipeline. `completed_at` tetap
     * disimpan sebagai waktu selesai, sedangkan `status` menjadi sumber
     * status bisnis (pending/process/completed).
     */
    public function up(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->string('status', 20)->default('pending')->after('completed_at');
            $table->text('outcome')->nullable()->after('status');
            $table->string('source', 20)->nullable()->after('outcome');

            $table->index(['created_by', 'status', 'follow_up_date'], 'follow_ups_owner_status_date_index');
            $table->index(['lead_id', 'status'], 'follow_ups_lead_status_index');
        });

        // Data lama: yang sudah punya completed_at dianggap selesai.
        // Sisanya tetap pending agar tidak mengubah antrean yang sedang berjalan.
        DB::table('follow_ups')->whereNotNull('completed_at')->update(['status' => 'completed']);
    }

    public function down(): void
    {
        Schema::table('follow_ups', function (Blueprint $table) {
            $table->dropIndex('follow_ups_owner_status_date_index');
            $table->dropIndex('follow_ups_lead_status_index');
            $table->dropColumn(['status', 'outcome', 'source']);
        });
    }
};
