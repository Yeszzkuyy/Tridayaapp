<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 3 — Penerimaan barang dari pemasok (aditif).
 *
 * `goods_receipts` = dokumen Terima Barang atas Supplier PO.
 * `goods_receipt_items` mencatat kuantitas fisik + Serial Number.
 * quantity_received pada item PO dihitung ulang dari receipt yang
 * berstatus verified (lihat ProcurementService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 50)->unique();
            $table->foreignId('supplier_purchase_order_id')
                ->constrained('supplier_purchase_orders', 'id', 'gr_spo_fk')
                ->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('received_date');
            $table->string('supplier_delivery_note_no')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_purchase_order_item_id')
                ->constrained('supplier_purchase_order_items', 'id', 'gri_spoi_fk')
                ->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity_received', 12, 2);
            $table->json('serial_numbers')->nullable();
            $table->string('condition_notes')->nullable();
            $table->timestamps();

            $table->index(['goods_receipt_id', 'supplier_purchase_order_item_id'], 'gri_gr_spoi_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
    }
};
