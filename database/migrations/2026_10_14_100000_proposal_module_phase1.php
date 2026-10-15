<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Proposal ronde 1 (aditif saja, tanpa rename/drop):
 * - products: master katalog barang & jasa untuk picker BOQ.
 * - proposals: +po_document_path (upload PO saat WON).
 * - proposal_items: +product_id nullable (item custom = null).
 * - customers: +npwp & +npwp_document_path (sekali isi, dipakai ulang).
 * - projects: +proposal_id (link balik hasil convert).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabel products sudah ada (milik modul AI-KB, berisi brand/model/status
        // review). Modul proposal memakai ulang tabel yang sama + kolom harga.
        // is_active default true agar produk KB lama otomatis bisa dipilih.
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'unit')) {
                $table->string('unit', 16)->default('pcs')->after('name');
            }
            if (! Schema::hasColumn('products', 'default_price')) {
                $table->decimal('default_price', 15, 2)->default(0)->after('unit');
            }
            if (! Schema::hasColumn('products', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('default_price');
            }
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->string('po_document_path')->nullable()->after('notes');
        });

        Schema::table('proposal_items', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->after('proposal_id')
                ->constrained()->nullOnDelete();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('npwp', 32)->nullable()->after('email');
            $table->string('npwp_document_path')->nullable()->after('npwp');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('proposal_id')->nullable()->after('quotation_number')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['proposal_id']);
            $table->dropColumn('proposal_id');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['npwp', 'npwp_document_path']);
        });

        Schema::table('proposal_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });

        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn('po_document_path');
        });

        // Kolom milik proposal di tabel products (tabelnya sendiri milik AI-KB).
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['unit', 'default_price', 'is_active']);
        });
    }
};
