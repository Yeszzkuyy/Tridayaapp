<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Harga supplier bisa masuk sebagai include PPN atau exclude PPN.
     * Harga modal yang dipakai menghitung margin SELALU harga sebelum PPN,
     * sedangkan harga asli yang diketik tetap disimpan untuk jejak.
     */
    public function up(): void
    {
        Schema::table('estimator_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('estimator_request_items', 'price_includes_tax')) {
                $table->boolean('price_includes_tax')->default(false)->after('price');
            }
            if (! Schema::hasColumn('estimator_request_items', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->nullable()->after('price_includes_tax');
            }
            if (! Schema::hasColumn('estimator_request_items', 'price_before_tax')) {
                $table->decimal('price_before_tax', 15, 2)->nullable()->after('tax_rate');
            }
        });

        Schema::table('product_price_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('product_price_logs', 'price_entered')) {
                $table->decimal('price_entered', 15, 2)->nullable()->after('price');
            }
            if (! Schema::hasColumn('product_price_logs', 'price_includes_tax')) {
                $table->boolean('price_includes_tax')->default(false)->after('price_entered');
            }
            if (! Schema::hasColumn('product_price_logs', 'tax_rate')) {
                $table->decimal('tax_rate', 5, 2)->nullable()->after('price_includes_tax');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'cost_includes_tax')) {
                $table->boolean('cost_includes_tax')->default(false)->after('cost_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('cost_includes_tax');
        });

        Schema::table('product_price_logs', function (Blueprint $table) {
            $table->dropColumn(['price_entered', 'price_includes_tax', 'tax_rate']);
        });

        Schema::table('estimator_request_items', function (Blueprint $table) {
            $table->dropColumn(['price_includes_tax', 'tax_rate', 'price_before_tax']);
        });
    }
};
