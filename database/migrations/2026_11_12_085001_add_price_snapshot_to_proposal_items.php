<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_items', function (Blueprint $table) {
            $table->foreignId('product_price_log_id')->nullable()->after('product_id')
                ->constrained('product_price_logs')->nullOnDelete();
            $table->string('stock_status', 16)->nullable()->after('lead_time');
            $table->string('stock_eta', 100)->nullable()->after('stock_status');
            $table->string('warranty_info', 255)->nullable()->after('stock_eta');
        });
    }

    public function down(): void
    {
        Schema::table('proposal_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_price_log_id');
            $table->dropColumn(['stock_status', 'stock_eta', 'warranty_info']);
        });
    }
};
