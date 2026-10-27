<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda jenis file dokumen customer:
     * null = dokumen umum, 'invoice' | 'po' | 'payment' = file
     * pengganti form Create Invoice / Create PO / Record Payment.
     *
     * Sekaligus menambahkan file_size & mime_type yang selama ini
     * diisi model tapi kolomnya tidak pernah dibuat (size tampil 0 KB).
     */
    public function up(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->string('doc_type', 20)->nullable()->after('customer_id');
            $table->index('doc_type');
            $table->unsignedBigInteger('file_size')->nullable()->after('file_path');
            $table->string('mime_type')->nullable()->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->dropIndex(['doc_type']);
            $table->dropColumn(['doc_type', 'file_size', 'mime_type']);
        });
    }
};
