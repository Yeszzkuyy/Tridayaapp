<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('brand')->nullable()->index();
            $table->string('name');
            $table->string('model')->nullable()->index();
            $table->string('sku')->nullable()->index();
            $table->string('category')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('status')->default('review')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
