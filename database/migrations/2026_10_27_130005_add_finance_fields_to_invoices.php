<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap 5 — Kolom finance pada invoice pelanggan (AR).
 *
 * `proposal_id` menautkan invoice ke Quotation Deal sehingga penagihan
 * DP/Termin/Pelunasan bisa dilacak. `invoice_type` membedakan jenis
 * penagihan, dan `paid_amount` menyimpan total pembayaran yang sudah
 * dialokasikan (di-update oleh AdminService::syncInvoiceStatus).
 *
 * Status `partial` ditambahkan dengan mengubah enum menjadi varchar.
 * PostgreSQL memakai USING cast, MySQL memakai MODIFY, SQLite memakai
 * rebuild tabel bawaan Laravel. Down() sengaja tidak mengembalikan enum
 * agar invoice berstatus `partial` tidak kehilangan data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('proposal_id')->nullable()->after('project_id')
                ->constrained()->nullOnDelete();
            $table->string('invoice_type', 20)->default('full')->after('proposal_id');
            $table->decimal('paid_amount', 15, 2)->default(0)->after('amount');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            // Laravel membuat enum Postgres sebagai varchar + check constraint.
            DB::statement('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_status_check');
            DB::statement('ALTER TABLE invoices ALTER COLUMN status TYPE varchar(20) USING status::varchar(20)');
            DB::statement("ALTER TABLE invoices ALTER COLUMN status SET DEFAULT 'unpaid'");
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE invoices MODIFY status VARCHAR(20) NOT NULL DEFAULT 'unpaid'");
        } else {
            // SQLite: rebuild tabel lewat schema builder (menghapus CHECK enum lama).
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('status', 20)->default('unpaid')->change();
            });
        }

        // Backfill paid_amount dari payments yang sudah ada. Tanpa ini invoice
        // lama akan terlihat belum dibayar setelah kolom baru ditambahkan.
        DB::table('invoices')
            ->select('id', 'amount', 'status')
            ->orderBy('id')
            ->chunkById(200, function ($invoices) {
                $totals = DB::table('payments')
                    ->whereIn('invoice_id', $invoices->pluck('id'))
                    ->whereNull('deleted_at')
                    ->selectRaw('invoice_id, COALESCE(SUM(amount), 0) as total')
                    ->groupBy('invoice_id')
                    ->pluck('total', 'invoice_id');

                foreach ($invoices as $invoice) {
                    $paid = round((float) $totals->get($invoice->id, 0), 2);
                    $status = $invoice->status;

                    // Status manual `paid`/`cancelled` dipertahankan; status lain
                    // diselaraskan dengan total pembayaran aktual.
                    if (! in_array($status, ['paid', 'cancelled'], true)) {
                        $status = $paid <= 0
                            ? 'unpaid'
                            : ($paid >= (float) $invoice->amount ? 'paid' : 'partial');
                    }

                    DB::table('invoices')->where('id', $invoice->id)->update([
                        'paid_amount' => $paid,
                        'status' => $status,
                    ]);
                }
            });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['status', 'due_date'], 'invoices_status_due_idx');
            $table->index('proposal_id', 'invoices_proposal_idx');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_status_due_idx');
            $table->dropIndex('invoices_proposal_idx');
            $table->dropConstrainedForeignId('proposal_id');
            $table->dropColumn(['invoice_type', 'paid_amount']);
        });
    }
};
