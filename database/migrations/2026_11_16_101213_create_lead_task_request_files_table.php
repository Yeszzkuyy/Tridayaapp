<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lampiran pendukung dari Sales saat meminta bantuan Inside Sales
     * (brief, requirement, dokumen acuan). Terpisah dari lampiran quotation
     * yang diunggah Inside Sales.
     */
    public function up(): void
    {
        Schema::create('lead_task_request_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_task_id')->constrained('lead_tasks')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_task_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_task_request_files');
    }
};
