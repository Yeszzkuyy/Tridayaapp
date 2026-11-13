<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda Overdue baru: lead yang di-assign lebih dari 24 jam dan belum
     * ditindaklanjuti. Hanya menyimpan kapan notifikasi terakhir dikirim;
     * status overdue sendiri dihitung dari assigned_at + follow-up.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('assignment_overdue_notified_at')->nullable()->after('acknowledged_at');
            $table->index(['assigned_to', 'assigned_at'], 'leads_assignee_assigned_index');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_assignee_assigned_index');
            $table->dropColumn('assignment_overdue_notified_at');
        });
    }
};
