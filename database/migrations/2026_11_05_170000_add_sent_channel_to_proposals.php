<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kanal pengiriman quotation: manual (tombol Mark as Sent), whatsapp, email.
 * Dipakai untuk badge "Terkirim via …" dan menyembunyikan tombol kirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->string('sent_channel', 20)->nullable()->after('sent_by');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn('sent_channel');
        });
    }
};
