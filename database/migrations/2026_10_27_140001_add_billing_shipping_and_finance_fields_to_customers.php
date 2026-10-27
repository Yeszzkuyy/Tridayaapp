<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelengkapan data penagihan & pengiriman pelanggan.
 *
 * - billing_address    : alamat yang dicetak di Invoice / Faktur Pajak.
 * - shipping_address   : alamat tujuan pengiriman barang (bisa beda dari
 *                        alamat kantor pusat).
 * - finance_pic_*      : kontak bagian keuangan yang dihubungi Admin
 *                        Finance saat menagih.
 *
 * Semua nullable -> customer lama tetap valid tanpa migrasi data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('billing_address')->nullable()->after('address');
            $table->text('shipping_address')->nullable()->after('billing_address');
            $table->string('finance_pic_name', 255)->nullable()->after('shipping_address');
            $table->string('finance_pic_phone', 50)->nullable()->after('finance_pic_name');
            $table->string('finance_pic_email', 255)->nullable()->after('finance_pic_phone');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'billing_address',
                'shipping_address',
                'finance_pic_name',
                'finance_pic_phone',
                'finance_pic_email',
            ]);
        });
    }
};
