<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Syarat bayar (TOP) & estimasi pengiriman pada quotation.
 *
 * - payment_scheme : kode mesin untuk prefill Invoice Admin
 *                    (full = lunas 100%, dp = DP + pelunasan, tempo = net days).
 * - dp_percent     : persentase DP bila scheme = dp.
 * - term_days      : jumlah hari tempo bila scheme = tempo.
 * - payment_terms  : teks yang dibaca pelanggan/admin (bisa custom).
 * - delivery_terms : estimasi pengiriman/inden.
 *
 * Semua nullable -> quotation lama tetap valid; convert ke project
 * mewajibkan payment_scheme agar Admin Finance tidak menebak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->string('payment_scheme', 20)->nullable()->after('delivery_address');
            $table->decimal('dp_percent', 5, 2)->nullable()->after('payment_scheme');
            $table->unsignedSmallInteger('term_days')->nullable()->after('dp_percent');
            $table->string('payment_terms', 255)->nullable()->after('term_days');
            $table->string('delivery_terms', 255)->nullable()->after('payment_terms');
        });
    }

    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn([
                'payment_scheme',
                'dp_percent',
                'term_days',
                'payment_terms',
                'delivery_terms',
            ]);
        });
    }
};
