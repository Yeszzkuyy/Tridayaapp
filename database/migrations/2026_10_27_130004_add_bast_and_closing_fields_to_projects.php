<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 5 — BAST & penutupan komersial proyek (aditif).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('requires_installation')->default(true)->after('progress');
            $table->string('bast_number', 100)->nullable()->unique()->after('requires_installation');
            $table->date('bast_date')->nullable()->after('bast_number');
            $table->foreignId('bast_document_id')->nullable()
                ->after('bast_date')
                ->constrained('project_documents', 'id', 'proj_bast_doc_fk')
                ->nullOnDelete();
            $table->date('delivery_deadline')->nullable()->after('bast_document_id');
            $table->timestamp('commercial_closed_at')->nullable()->after('delivery_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bast_document_id');
            $table->dropUnique(['bast_number']);
            $table->dropColumn([
                'requires_installation',
                'bast_number',
                'bast_date',
                'delivery_deadline',
                'commercial_closed_at',
            ]);
        });
    }
};
