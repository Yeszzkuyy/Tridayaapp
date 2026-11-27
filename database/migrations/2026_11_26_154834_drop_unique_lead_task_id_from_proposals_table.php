<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisi quotation menambah baris proposal baru dengan `lead_task_id` yang
 * sama, sehingga relasi task → quotation tidak lagi satu-satu. Keunikan
 * dokumen sekarang dijaga oleh (company_code, quotation_year,
 * sequence_number) + is_latest, bukan oleh lead_task_id.
 *
 * Beberapa database menyimpan unique sebagai constraint, sebagian sebagai
 * index biasa, jadi penghapusannya dibuat tahan terhadap keduanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropIndexIfExists('proposals_lead_task_id_unique');

        if (! $this->indexExists('proposals_lead_task_id_index')) {
            Schema::table('proposals', function (Blueprint $table) {
                $table->index('lead_task_id');
            });
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('proposals_lead_task_id_index');

        if (! $this->indexExists('proposals_lead_task_id_unique')) {
            Schema::table('proposals', function (Blueprint $table) {
                $table->unique('lead_task_id');
            });
        }
    }

    private function dropIndexIfExists(string $name): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Unique bisa tersimpan sebagai constraint atau index biasa.
            DB::statement('ALTER TABLE "proposals" DROP CONSTRAINT IF EXISTS "'.$name.'"');
            DB::statement('DROP INDEX IF EXISTS "'.$name.'"');

            return;
        }

        DB::statement('DROP INDEX IF EXISTS "'.$name.'"');
    }

    private function indexExists(string $name): bool
    {
        return match (DB::getDriverName()) {
            'pgsql' => DB::table('pg_indexes')
                ->where('tablename', 'proposals')
                ->where('indexname', $name)
                ->exists(),
            'sqlite' => (bool) DB::selectOne(
                "select name from sqlite_master where type = 'index' and name = ?",
                [$name],
            ),
            default => Schema::hasIndex('proposals', $name),
        };
    }
};
