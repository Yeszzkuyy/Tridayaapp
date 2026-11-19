<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alur BAST:
     * 1. Teknisi menyelesaikan instalasi (completed_at/completed_by).
     * 2. Admin memeriksa kelengkapan, membuat BAST dari template, lalu
     *    mengunggah dokumen yang sudah ditandatangani.
     * 3. Tanda tangan 3DY + pelanggan dicatat agar Finance tahu dokumen
     *    sudah lengkap sebelum proses berikutnya.
     */
    public function up(): void
    {
        Schema::table('instalasis', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('status');
            $table->foreignId('completed_by')->nullable()->after('completed_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('bast_signed_3dy_name', 150)->nullable()->after('bast_date');
            $table->timestamp('bast_signed_3dy_at')->nullable()->after('bast_signed_3dy_name');
            $table->string('bast_signed_customer_name', 150)->nullable()->after('bast_signed_3dy_at');
            $table->timestamp('bast_signed_customer_at')->nullable()->after('bast_signed_customer_name');
            $table->timestamp('bast_ready_at')->nullable()->after('bast_signed_customer_at');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'bast_signed_3dy_name',
                'bast_signed_3dy_at',
                'bast_signed_customer_name',
                'bast_signed_customer_at',
                'bast_ready_at',
            ]);
        });

        Schema::table('instalasis', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
            $table->dropColumn('completed_at');
        });
    }
};
