<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request Harga: sales melampirkan file Quotation miliknya + customer (PT)
 * + catatan; tim engineer membalasnya dengan file hasil hitungan.
 * Terpisah total dari technical_requests (Request Tim Engineer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('quotation_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('waiting_lead');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reply_path')->nullable();
            $table->text('reply_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_requests');
    }
};
