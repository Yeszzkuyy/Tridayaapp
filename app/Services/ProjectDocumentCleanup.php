<?php

namespace App\Services;

use App\Models\Instalasi;
use App\Models\Payment;
use App\Models\ProjectDocument;
use App\Models\SizingProject;
use App\Models\Survey;
use Illuminate\Support\Facades\Storage;

/**
 * Penghapusan permanen dokumen: menghapus file fisik, melepas tautan file
 * milik modul (survey/sizing/instalasi), dan membersihkan baris DB.
 *
 * Dipakai saat hapus permanen dari Trash dan oleh command pembersih otomatis
 * (documents:purge-trash) supaya file tidak pernah tertinggal.
 */
class ProjectDocumentCleanup
{
    /**
     * Hapus permanen satu dokumen beserta file dan tautannya.
     */
    public function forceDelete(ProjectDocument $document): void
    {
        // Dokumen milik modul: lepas dulu tautan file di record modulnya,
        // supaya tidak didaftarkan ulang oleh DocumentRegistry::sync dan
        // menu modul tidak menunjuk file yang sudah hilang.
        if ($document->isModuleDocument()) {
            $this->releaseModuleFile($document);
        }

        if ($document->file_path && Storage::disk('private')->exists($document->file_path)) {
            Storage::disk('private')->delete($document->file_path);
        }

        // File payment dipakai juga sebagai bukti transfer (path sama).
        // Lepas tautannya supaya tombol Proof tidak menunjuk file yang hilang.
        $linked = $document->documentable;
        if ($linked instanceof Payment && $linked->proof_file === $document->file_path) {
            $linked->update(['proof_file' => null]);
        }

        $document->forceDelete();
    }

    /**
     * Hapus permanen dokumen di Trash yang sudah melewati masa simpan.
     *
     * @return int jumlah dokumen yang dihapus
     */
    public function purgeExpired(int $days = ProjectDocument::TRASH_RETENTION_DAYS): int
    {
        $count = 0;
        $deadline = now()->subDays($days);

        ProjectDocument::onlyTrashed()
            ->where('deleted_at', '<=', $deadline)
            ->orderBy('id')
            ->chunkById(100, function ($documents) use (&$count) {
                foreach ($documents as $document) {
                    $this->forceDelete($document);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Hapus permanen semua dokumen (aktif maupun terarsip) milik satu
     * customer/project — dipakai sebelum customer/project dihapus permanen
     * supaya file fisiknya tidak tertinggal.
     */
    public function purgeForCustomer(int $customerId): int
    {
        $count = 0;

        ProjectDocument::withTrashed()
            ->where('customer_id', $customerId)
            ->orderBy('id')
            ->chunkById(100, function ($documents) use (&$count) {
                foreach ($documents as $document) {
                    $this->forceDelete($document);
                    $count++;
                }
            });

        return $count;
    }

    public function purgeForProject(int $projectId): int
    {
        $count = 0;

        ProjectDocument::withTrashed()
            ->where('project_id', $projectId)
            ->orderBy('id')
            ->chunkById(100, function ($documents) use (&$count) {
                foreach ($documents as $document) {
                    $this->forceDelete($document);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Cabut referensi file dari record pemilik modul (survey/instalasi satu
     * file, sizing berupa daftar lampiran). File fisik dihapus terpisah.
     */
    private function releaseModuleFile(ProjectDocument $document): void
    {
        $owner = $document->documentable;

        if ($owner instanceof Survey || $owner instanceof Instalasi) {
            if ($owner->report_file === $document->file_path) {
                $owner->forceFill([
                    'report_file' => null,
                    'report_file_name' => null,
                ])->save();
            }

            return;
        }

        if ($owner instanceof SizingProject) {
            $remaining = collect($owner->attachments ?? [])
                ->reject(function ($attachment) use ($document) {
                    $path = is_array($attachment)
                        ? ($attachment['file_path'] ?? $attachment['path'] ?? null)
                        : $attachment;

                    return $path === $document->file_path;
                })
                ->values()
                ->all();

            $owner->forceFill(['attachments' => $remaining])->save();
        }
    }
}
