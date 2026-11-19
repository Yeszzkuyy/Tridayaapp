<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran file quotation untuk menu Quotation (proposal). Dipakai saat
 * membuat quotation lewat upload file, dan saat menerima file dari Inside Sales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposals')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['proposal_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_attachments');
    }
};
