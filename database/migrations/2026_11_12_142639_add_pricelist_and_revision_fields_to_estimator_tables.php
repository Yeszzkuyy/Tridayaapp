<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estimator_requests', function (Blueprint $table) {
            $table->text('revision_reason')->nullable()->after('notes');
            $table->timestamp('revision_requested_at')->nullable()->after('revision_reason');
        });

        Schema::table('estimator_request_items', function (Blueprint $table) {
            $table->date('valid_until')->nullable()->after('warranty_info');
        });

        Schema::table('product_price_logs', function (Blueprint $table) {
            $table->date('valid_until')->nullable()->after('warranty_info');
        });
    }

    public function down(): void
    {
        Schema::table('product_price_logs', function (Blueprint $table) {
            $table->dropColumn('valid_until');
        });

        Schema::table('estimator_request_items', function (Blueprint $table) {
            $table->dropColumn('valid_until');
        });

        Schema::table('estimator_requests', function (Blueprint $table) {
            $table->dropColumn(['revision_reason', 'revision_requested_at']);
        });
    }
};
