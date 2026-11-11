<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support engineer untuk Request Tim Engineer.
     *
     * Satu request tetap punya satu engineer utama
     * (technical_requests.assigned_technician_id); tabel ini menyimpan
     * engineer pendukung yang jumlahnya tidak dibatasi.
     */
    public function up(): void
    {
        Schema::create('technical_request_supports', function (Blueprint $table) {
            $table->id();
            // Tanpa foreign key constraint: akun database aplikasi di server
            // ini tidak memegang izin REFERENCES pada tabel induk. Relasi
            // tetap dijaga di level aplikasi (kedua model memakai soft delete).
            $table->foreignId('technical_request_id');
            $table->foreignId('user_id')->index();
            $table->timestamps();

            $table->unique(['technical_request_id', 'user_id'], 'tech_request_support_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technical_request_supports');
    }
};
