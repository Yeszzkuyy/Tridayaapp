<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX = 'technician_schedules_google_event_id_active_unique';

    public function up(): void
    {
        // Partial unique index (PostgreSQL): jamin keunikan google_event_id
        // hanya untuk baris AKTIF. Baris soft-delete tetap boleh menyimpan
        // google_event_id yang sama (alur restore). Menambah index saja —
        // tidak mengubah/menghapus data apa pun.
        DB::statement(
            'CREATE UNIQUE INDEX '.self::INDEX.'
             ON technician_schedules (google_event_id)
             WHERE google_event_id IS NOT NULL AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        // Hapus index saja — tidak menyentuh data.
        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
    }
};
