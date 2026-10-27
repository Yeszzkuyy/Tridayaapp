<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 1 — pelacakan kuantitas keluar per item Supplier PO.
 *
 * `quantity_delivered` dihitung ulang dari seluruh GoodsDeliveryItem yang
 * masih aktif (delivery tidak dihapus). Nilai ini dipakai untuk:
 * - memastikan delivery tidak melebihi barang yang sudah diterima;
 * - mengetahui sisa kewajiban pengiriman saat evaluasi closing proyek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_purchase_order_items', function (Blueprint $table) {
            $table->decimal('quantity_delivered', 12, 2)->default(0)->after('quantity_received');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('quantity_delivered');
        });
    }
};
