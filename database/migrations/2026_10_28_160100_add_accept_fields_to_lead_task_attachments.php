<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Status "diterima" untuk lampiran quotation Inside Sales + tautan ke
 * Quotation (proposals) yang dibuat otomatis saat file di-accept.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_task_attachments', function (Blueprint $table) {
            $table->foreignId('proposal_id')->nullable()->after('lead_task_id')
                ->constrained('proposals')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->after('uploaded_by')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable()->after('accepted_by');
        });
    }

    public function down(): void
    {
        Schema::table('lead_task_attachments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proposal_id');
            $table->dropConstrainedForeignId('accepted_by');
            $table->dropColumn('accepted_at');
        });
    }
};
