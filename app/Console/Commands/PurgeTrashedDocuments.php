<?php

namespace App\Console\Commands;

use App\Models\ProjectDocument;
use App\Services\ProjectDocumentCleanup;
use Illuminate\Console\Command;

/**
 * Hapus permanen dokumen yang sudah melewati masa simpan di Trash.
 *
 * Dihitung dari deleted_at + ProjectDocument::TRASH_RETENTION_DAYS (14 hari),
 * jadi file tidak terhapus sebelum genap 2 minggu dan tidak menggantung
 * selamanya bila scheduler berjalan terlambat.
 */
class PurgeTrashedDocuments extends Command
{
    protected $signature = 'documents:purge-trash {--days= : Masa simpan (hari), default 14}';

    protected $description = 'Hapus permanen dokumen di Trash yang sudah melewati 14 hari';

    public function handle(ProjectDocumentCleanup $cleanup): int
    {
        $days = (int) ($this->option('days') ?? ProjectDocument::TRASH_RETENTION_DAYS);
        if ($days < 1) {
            $days = ProjectDocument::TRASH_RETENTION_DAYS;
        }

        $count = $cleanup->purgeExpired($days);

        $this->info("Selesai. {$count} dokumen dihapus permanen (masa simpan {$days} hari).");

        return self::SUCCESS;
    }
}
