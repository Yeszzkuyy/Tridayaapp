<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot data komersial saat invoice dibuat. Perubahan alamat billing
     * atau syarat bayar customer/quotation setelah invoice terbit tidak boleh
     * mengubah isi invoice lama.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->text('billing_address')->nullable()->after('customer_po_number');
            $table->text('payment_terms')->nullable()->after('billing_address');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['billing_address', 'payment_terms']);
        });
    }
};
