<?php

namespace App\Console\Commands;

use App\Models\Instalasi;
use App\Models\SizingProject;
use App\Models\Survey;
use App\Services\DocumentRegistry;
use Illuminate\Console\Command;

/**
 * Daftarkan file yang sudah tersimpan di modul (laporan survey, lampiran
 * sizing, laporan instalasi) ke daftar dokumen — untuk data lama yang lahir
 * sebelum sinkron otomatis aktif.
 *
 * Idempoten: dijalankan berkali-kali tidak menggandakan dokumen. Dokumen
 * yang sama sudah diunggah manual lewat menu Documents akan digabung
 * (bukan ditambah baru) bila nama + ukuran filenya cocok.
 */
class SyncModuleDocuments extends Command
{
    protected $signature = 'documents:sync-modules';

    protected $description = 'Sinkronkan file modul (survey/sizing/instalasi) ke daftar dokumen tanpa duplikat';

    public function handle(): int
    {
        $created = 0;

        $sync = function ($owner) use (&$created) {
            $before = DocumentRegistry::filesOf($owner);
            DocumentRegistry::sync($owner);
            $created += count($before);
        };

        Survey::query()->orderBy('id')->chunk(100, function ($surveys) use ($sync) {
            $surveys->each($sync);
        });

        SizingProject::query()->orderBy('id')->chunk(100, function ($sizings) use ($sync) {
            $sizings->each($sync);
        });

        Instalasi::query()->orderBy('id')->chunk(100, function ($instalasis) use ($sync) {
            $instalasis->each($sync);
        });

        $this->info("Selesai. {$created} file modul disinkronkan ke daftar dokumen.");

        return self::SUCCESS;
    }
}
