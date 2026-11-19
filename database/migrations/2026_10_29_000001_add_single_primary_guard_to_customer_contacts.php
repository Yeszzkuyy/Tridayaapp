<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Jaminan "maksimal satu kontak utama per customer" di level database.
     *
     * Index parsial dipakai supaya kontak yang sudah soft-delete tidak ikut
     * dihitung. PostgreSQL dan SQLite sama-sama mendukung partial index.
     */
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS customer_contacts_one_primary
             ON customer_contacts (customer_id)
             WHERE is_primary = true AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS customer_contacts_one_primary');
    }
};
