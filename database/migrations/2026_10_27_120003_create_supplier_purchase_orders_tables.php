<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 2 — Supplier Purchase Order (pesanan 3DY KEPADA PEMASOK), aditif.
 *
 * Sengaja terpisah dari `purchase_orders` yang saat ini adalah PO sisi
 * pelanggan (`customer_id` + items teks bebas). Tabel lama TIDAK diubah,
 * tidak di-rename, dan tidak dipakai ulang.
 *
 * Item PO menyimpan snapshot supaya perubahan master produk tidak
 * mengubah dokumen historis. `quantity_received` disiapkan untuk Tahap 3
 * (penerimaan barang, termasuk penerimaan sebagian).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('issue_date');
            $table->date('expected_delivery_date')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['proposal_id', 'supplier_id']);
            $table->index(['status', 'expected_delivery_date']);
        });

        Schema::create('supplier_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_purchase_order_id')
                ->constrained('supplier_purchase_orders', 'id', 'spo_items_order_fk')
                ->cascadeOnDelete();
            // Satu item quotation boleh menghasilkan BANYAK komponen pengadaan.
            $table->foreignId('proposal_item_id')->nullable()
                ->constrained('proposal_items')->nullOnDelete();
            $table->foreignId('product_id')->nullable()
                ->constrained('products')->nullOnDelete();

            // Snapshot transaksi — tidak ikut berubah saat master produk berubah.
            $table->string('item_name');
            $table->string('part_number', 120)->nullable();
            $table->text('description')->nullable();
            $table->string('unit', 16)->default('pcs');
            $table->decimal('quantity_ordered', 12, 2);
            $table->decimal('quantity_received', 12, 2)->default(0);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            $table->index('proposal_item_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_purchase_order_items');
        Schema::dropIfExists('supplier_purchase_orders');
    }
};
