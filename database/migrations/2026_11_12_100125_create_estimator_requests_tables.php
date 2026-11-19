<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('estimator_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('proposal_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('submitted');
            $table->text('notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index(['requested_by', 'status']);
            $table->index(['handled_by', 'status']);
        });

        Schema::create('estimator_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimator_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 15, 2)->default(1);
            $table->string('unit', 16)->default('pcs');
            $table->decimal('price', 15, 2)->nullable();
            $table->string('stock_status', 16)->nullable();
            $table->string('stock_eta', 100)->nullable();
            $table->string('warranty_info', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignId('answered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['estimator_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('estimator_request_items');
        Schema::dropIfExists('estimator_requests');
    }
};
