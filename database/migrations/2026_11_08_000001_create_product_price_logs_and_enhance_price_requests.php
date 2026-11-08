<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Histori harga produk (real-time) + relasi tambahan pada Price Request.
 *
 * Setiap perubahan harga dari Master Produk maupun balasan Estimator
 * dicatat sebagai baris baru; harga lama tidak ditimpa sehingga sales
 * bisa membandingkan versi harga dari supplier berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->decimal('price', 15, 2);
            $table->string('currency', 10)->default('IDR');
            $table->foreignId('price_request_id')->nullable()->constrained('price_requests')->nullOnDelete();
            $table->foreignId('updated_by')->constrained('users')->cascadeOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['supplier_id', 'created_at']);
        });

        if (! Schema::hasColumn('price_requests', 'lead_id')) {
            Schema::table('price_requests', function (Blueprint $table) {
                $table->foreignId('lead_id')->nullable()->after('customer_id')
                    ->constrained('leads')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('price_requests', 'project_id')) {
            Schema::table('price_requests', function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->after('lead_id')
                    ->constrained('projects')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('price_requests', 'handled_by')) {
            Schema::table('price_requests', function (Blueprint $table) {
                // Estimator penanggung jawab (assigned_to tetap dipakai
                // mesin status lama agar kompatibel).
                $table->foreignId('handled_by')->nullable()->after('assigned_to')
                    ->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('price_requests', 'handled_by')) {
            Schema::table('price_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('handled_by');
            });
        }

        if (Schema::hasColumn('price_requests', 'project_id')) {
            Schema::table('price_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('project_id');
            });
        }

        if (Schema::hasColumn('price_requests', 'lead_id')) {
            Schema::table('price_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('lead_id');
            });
        }

        Schema::dropIfExists('product_price_logs');
    }
};
