<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket 3 — Tahap 1: data customer PO pada quotation (aditif).
 *
 * Customer PO = pesanan dari pelanggan kepada 3DY (BUKAN Supplier PO —
 * tabel purchase_orders tetap terpisah dan tidak disentuh).
 *
 * - po_number          : nomor PO dari pelanggan.
 * - po_date            : tanggal customer PO.
 * - delivery_deadline  : tanggal/tenggat pengiriman yang diminta pelanggan.
 * - delivery_address   : alamat/lokasi pengiriman.
 *
 * Semua nullable → quotation lama tetap kompatibel tanpa migrasi data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->string('po_number', 100)->nullable()->after('po_document_path');
            $table->date('po_date')->nullable()->after('po_number');
            $table->date('delivery_deadline')->nullable()->after('po_date');
            $table->string('delivery_address', 255)->nullable()->after('delivery_deadline');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['po_number', 'po_date', 'delivery_deadline', 'delivery_address']);
        });
    }
};
