<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot nomor PO/SPK pelanggan pada Invoice.
 *
 * Disimpan di invoice (bukan hanya dibaca dari quotation) supaya nomor
 * yang tercetak di lembar tagihan tidak berubah bila quotation diedit.
 * Nullable -> invoice lama tetap valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('customer_po_number', 100)->nullable()->after('proposal_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('customer_po_number');
        });
    }
};
