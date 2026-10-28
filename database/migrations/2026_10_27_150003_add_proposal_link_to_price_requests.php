<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batch 4 — tautan terstruktur Price Request ke Quotation.
 *
 * Price Request tetap menyimpan file quotation/balasan engineer, tetapi
 * quotation yang lahir dari request tersebut dapat dilacak melalui FK ini.
 * Tidak ada perubahan pada data historis; request lama tetap valid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_requests', function (Blueprint $table) {
            $table->foreignId('proposal_id')->nullable()->after('customer_id')
                ->constrained('proposals')->nullOnDelete();
            $table->index('proposal_id');
        });
    }

    public function down(): void
    {
        Schema::table('price_requests', function (Blueprint $table) {
            $table->dropForeign(['proposal_id']);
            $table->dropColumn('proposal_id');
        });
    }
};
