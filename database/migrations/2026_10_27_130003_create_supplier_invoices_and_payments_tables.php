<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 4 — Invoice Supplier (AP) & Pembayaran ke Supplier (uang keluar).
 *
 * Terpisah tegas dari `invoices`/`payments` yang merupakan AR pelanggan.
 * `goods_receipt_id` boleh kosong untuk invoice DP yang terbit sebelum
 * barang datang. Unique (supplier_id, supplier_invoice_number) mencegah
 * nomor invoice supplier yang sama diinput dua kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('internal_ref_number', 50)->unique();
            $table->string('supplier_invoice_number', 100);
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_purchase_order_id')
                ->constrained('supplier_purchase_orders', 'id', 'si_spo_fk')
                ->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()
                ->constrained('goods_receipts', 'id', 'si_gr_fk')
                ->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_type', 20)->default('full');
            $table->decimal('amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('status', 20)->default('unpaid')->index();
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            $table->string('invoice_file_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['supplier_id', 'supplier_invoice_number'], 'supplier_invoice_unique');
            $table->index(['status', 'due_date']);
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_purchase_order_id')->nullable()
                ->constrained('supplier_purchase_orders', 'id', 'spay_spo_fk')
                ->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('payment_method')->nullable();
            $table->string('proof_file')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_invoices');
    }
};
