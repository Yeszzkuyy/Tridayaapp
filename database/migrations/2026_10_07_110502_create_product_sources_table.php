<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('url', 2048)->unique();
            $table->string('source_type')->default('product_page')->index();
            $table->string('status')->default('queued')->index();
            $table->text('error')->nullable();
            $table->timestamp('last_fetched_at')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->uuid('batch_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_sources');
    }
};
