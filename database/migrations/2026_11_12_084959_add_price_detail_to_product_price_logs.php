<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_price_logs', function (Blueprint $table) {
            $table->string('price_type', 16)->default('cost')->after('price');
            $table->string('stock_status', 16)->nullable()->after('currency');
            $table->string('stock_eta', 100)->nullable()->after('stock_status');
            $table->string('warranty_info', 255)->nullable()->after('stock_eta');
        });

        // Baris lama tanpa price_request berasal dari perubahan harga jual di
        // Master Produk; tandai sebagai selling agar tidak masuk riwayat modal.
        DB::table('product_price_logs')
            ->whereNull('price_request_id')
            ->update(['price_type' => 'selling']);
    }

    public function down(): void
    {
        Schema::table('product_price_logs', function (Blueprint $table) {
            $table->dropColumn(['price_type', 'stock_status', 'stock_eta', 'warranty_info']);
        });
    }
};
