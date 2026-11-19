<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_price_logs', function (Blueprint $table) {
            $table->foreignId('estimator_request_item_id')->nullable()->after('price_request_id')
                ->constrained('estimator_request_items')->nullOnDelete();
            $table->index('estimator_request_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_price_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('estimator_request_item_id');
        });
    }
};
