<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks pendukung Document Center (aditif, tidak mengubah data).
 *
 * Query lintas modul memfilter/mengurutkan per customer, project, dan waktu
 * unggah. Di PostgreSQL foreign key TIDAK membuat indeks otomatis, jadi
 * kolom-kolom ini perlu indeks agar daftar dokumen tetap cepat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_documents')) {
            Schema::table('project_documents', function (Blueprint $table) {
                $table->index(['customer_id', 'created_at'], 'pd_customer_created_idx');
                $table->index(['project_id', 'created_at'], 'pd_project_created_idx');
            });
        }

        if (Schema::hasTable('lead_documents')) {
            Schema::table('lead_documents', function (Blueprint $table) {
                $table->index('lead_id', 'lead_documents_lead_idx');
            });
        }

        if (Schema::hasTable('supplier_payments')) {
            Schema::table('supplier_payments', function (Blueprint $table) {
                $table->index('supplier_invoice_id', 'supplier_payments_invoice_idx');
            });
        }

        if (Schema::hasTable('goods_receipts')) {
            Schema::table('goods_receipts', function (Blueprint $table) {
                $table->index('project_id', 'goods_receipts_project_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('project_documents')) {
            Schema::table('project_documents', function (Blueprint $table) {
                $table->dropIndex('pd_customer_created_idx');
                $table->dropIndex('pd_project_created_idx');
            });
        }

        if (Schema::hasTable('lead_documents')) {
            Schema::table('lead_documents', function (Blueprint $table) {
                $table->dropIndex('lead_documents_lead_idx');
            });
        }

        if (Schema::hasTable('supplier_payments')) {
            Schema::table('supplier_payments', function (Blueprint $table) {
                $table->dropIndex('supplier_payments_invoice_idx');
            });
        }

        if (Schema::hasTable('goods_receipts')) {
            Schema::table('goods_receipts', function (Blueprint $table) {
                $table->dropIndex('goods_receipts_project_idx');
            });
        }
    }
};
