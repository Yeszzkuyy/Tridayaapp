<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 2 — kunci idempotensi dokumen keuangan.
 *
 * Form membuat key unik per render. Submit ganda (double click/retry)
 * dengan key yang sama tidak membuat invoice/payment kedua.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'payments', 'supplier_invoices', 'supplier_payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('idempotency_key', 64)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'payments', 'supplier_invoices', 'supplier_payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['idempotency_key']);
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
