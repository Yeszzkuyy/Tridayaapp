<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket 2 — Tahap 1: harga modal & margin quotation (aditif).
 *
 * - products.cost_price     : harga modal produk (sumber snapshot).
 * - proposal_items.unit_cost: snapshot harga modal per item — perubahan harga
 *   produk TIDAK mengubah quotation historis.
 * - proposals.total_cost / gross_profit / gross_margin :
 *   NULL = perhitungan belum lengkap (item ada yang belum punya harga modal,
 *   atau data historis sebelum fitur ini). Jangan diisi 0 secara otomatis.
 *
 * Tipe decimal(15,2) konsisten dengan kolom uang yang sudah ada.
 * Kolom semua nullable → data lama tidak perlu diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('cost_price', 15, 2)->nullable()->after('default_price');
        });

        Schema::table('proposal_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->nullable()->after('unit_price');
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->decimal('total_cost', 15, 2)->nullable()->after('grand_total');
            $table->decimal('gross_profit', 15, 2)->nullable()->after('total_cost');
            $table->decimal('gross_margin', 8, 2)->nullable()->after('gross_profit');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['total_cost', 'gross_profit', 'gross_margin']);
        });

        Schema::table('proposal_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
