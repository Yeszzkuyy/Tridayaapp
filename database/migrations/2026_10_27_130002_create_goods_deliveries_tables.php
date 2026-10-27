<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 3 — Pengiriman barang keluar ke pelanggan (Surat Jalan).
 * Tertaut ke proyek/customer supaya penelusuran per proyek bisa dilakukan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('delivery_number', 50)->unique();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('delivery_date');
            $table->string('recipient_name')->nullable();
            $table->string('status', 20)->default('prepared')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('goods_delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_purchase_order_item_id')->nullable()
                ->constrained('supplier_purchase_order_items', 'id', 'gdi_spoi_fk')
                ->nullOnDelete();
            $table->string('item_name');
            $table->string('part_number', 120)->nullable();
            $table->string('unit', 16)->default('pcs');
            $table->decimal('quantity', 12, 2);
            $table->json('serial_numbers')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_delivery_items');
        Schema::dropIfExists('goods_deliveries');
    }
};
