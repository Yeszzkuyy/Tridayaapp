<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran quotation untuk task Inside Sales. File disimpan di disk privat,
 * tabel ini hanya menyimpan metadata + riwayat versi (tidak menimpa versi lama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_task_attachments', function (Blueprint $table) {
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
        Schema::dropIfExists('lead_task_attachments');
    }
};
