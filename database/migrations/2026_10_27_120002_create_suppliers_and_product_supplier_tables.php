<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 2 — Master Pemasok (aditif, terpisah dari `partners`).
 *
 * `partners` adalah relasi marketing (sumber lead) — bukan pemasok
 * pengadaan. Karena itu pemasok dibuat di tabel sendiri.
 *
 * `product_suppliers` mendukung satu produk dibeli dari BANYAK pemasok.
 * `is_primary` hanya penanda preferensi; Purchasing tetap boleh memilih
 * pemasok lain pada transaksi aktual (Supplier PO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->string('supplier_part_number', 120)->nullable();
            $table->decimal('last_cost_price', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'supplier_id']);
            $table->index(['supplier_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_suppliers');
        Schema::dropIfExists('suppliers');
    }
};
