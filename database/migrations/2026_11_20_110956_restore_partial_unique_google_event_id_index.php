<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEX = 'technician_schedules_google_event_id_active_unique';

    /**
     * SQLite membangun ulang tabel saat foreign key ditambahkan, dan proses
     * itu mengubah partial unique index menjadi unique index biasa. Index
     * biasa menolak baris soft-delete yang memakai google_event_id sama,
     * sehingga alur restore jadwal gagal. Migration ini mengembalikan index
     * ke bentuk partial tanpa menyentuh data.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $current = DB::selectOne(
                "SELECT sql FROM sqlite_master WHERE type = 'index' AND name = ?",
                [self::INDEX]
            );

            if ($current && str_contains((string) ($current->sql ?? ''), 'deleted_at IS NULL')) {
                return;
            }
        } elseif ($driver === 'pgsql') {
            $current = DB::selectOne(
                "SELECT indexdef FROM pg_indexes WHERE tablename = 'technician_schedules' AND indexname = ?",
                [self::INDEX]
            );

            if ($current && str_contains((string) ($current->indexdef ?? ''), 'deleted_at IS NULL')) {
                return;
            }
        } else {
            // MySQL tidak mendukung partial index. Jangan ubah apa pun.
            return;
        }

        // Jangan hapus data. Bila sudah ada duplikat aktif, biarkan index
        // lama dan perbaiki datanya lebih dulu sebelum migration dijalankan
        // ulang.
        $hasDuplicates = DB::table('technician_schedules')
            ->select('google_event_id')
            ->whereNotNull('google_event_id')
            ->whereNull('deleted_at')
            ->groupBy('google_event_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS '.self::INDEX);
        DB::statement(
            'CREATE UNIQUE INDEX '.self::INDEX.'
             ON technician_schedules (google_event_id)
             WHERE google_event_id IS NOT NULL AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        // Index partial tetap dipertahankan: menghapusnya akan membuka
        // kembali celah duplikat google_event_id pada baris aktif.
    }
};
