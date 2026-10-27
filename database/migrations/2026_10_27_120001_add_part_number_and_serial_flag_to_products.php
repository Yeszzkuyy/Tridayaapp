<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 2 — Master Data Barang: Part Number & penanda nomor seri (aditif).
 *
 * - part_number       : PN resmi dari produsen/pemasok (dipakai Purchasing).
 * - has_serial_number : penanda produk yang wajib dicatat nomor serinya
 *                       (dipakai Tahap 3 — penerimaan/pengiriman).
 *
 * Harga modal TIDAK ditambahkan di sini: sudah ada `products.cost_price`
 * dari Paket 2 (nama kanonis, tidak ada kolom duplikat `buy_price`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('part_number', 120)->nullable()->after('model')->index();
            $table->boolean('has_serial_number')->default(false)->after('cost_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['part_number']);
            $table->dropColumn(['part_number', 'has_serial_number']);
        });
    }
};
