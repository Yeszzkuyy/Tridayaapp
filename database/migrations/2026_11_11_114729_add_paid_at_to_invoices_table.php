<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoices', 'paid_at')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('status');
        });

        // Invoice lama yang sudah lunas: pakai tanggal pembayaran terakhir.
        // Bila tidak ada baris pembayaran, pakai updated_at sebagai fallback.
        DB::table('invoices')
            ->where('status', 'paid')
            ->update([
                'paid_at' => DB::raw('COALESCE((SELECT MAX(p.payment_date) FROM payments p WHERE p.invoice_id = invoices.id AND p.deleted_at IS NULL), invoices.updated_at)'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('invoices', 'paid_at')) {
            return;
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
