<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_activities', function (Blueprint $table) {
            $table->id();
            // Tanpa foreign key: riwayat harus tetap ada walau dokumen
            // sudah dihapus permanen.
            $table->unsignedBigInteger('document_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 24);
            $table->string('file_name');
            $table->string('doc_type')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_activities');
    }
};
