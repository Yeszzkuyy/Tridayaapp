<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tautkan file invoice/PO/payment ke record datanya.
     *
     * Sebelumnya file hanya menempel di customer (doc_type). Dengan kolom
     * documentable, file yang diunggah dari menu admin ikut terhubung ke
     * Invoice/PurchaseOrder/Payment yang bersangkutan, sehingga detail
     * record bisa menampilkan filenya — sementara tab Documents customer
     * tetap menampilkannya lewat doc_type.
     */
    public function up(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->nullableMorphs('documentable');
        });
    }

    public function down(): void
    {
        Schema::table('project_documents', function (Blueprint $table) {
            $table->dropIndex(['documentable_type', 'documentable_id']);
            $table->dropColumn(['documentable_type', 'documentable_id']);
        });
    }
};
