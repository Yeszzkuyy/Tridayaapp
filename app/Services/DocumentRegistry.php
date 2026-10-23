<?php

namespace App\Services;

use App\Models\DocumentCategory;
use App\Models\Instalasi;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\SizingProject;
use App\Models\Survey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Penjaga daftar dokumen tunggal (project_documents).
 *
 * File yang diunggah lewat menu modul (Survey, Sizing, Instalasi) disimpan
 * SEKALI di disk privat, lalu dicatat di sini supaya ikut muncul di semua
 * menu dokumen — Customer > Documents, Project Documents, dan Document
 * Repository — tanpa menyalin file dan tanpa duplikat.
 *
 * Aturan anti-duplikat (satu file = satu baris dokumen), berurutan:
 *  1. baris yang sudah tertaut ke pemilik + path file yang sama → diperbarui;
 *  2. pemilik satu-file (laporan survey/instalasi) yang mengganti laporan
 *     → barisnya diperbarui, bukan ditambah;
 *  3. dokumen yang sama sudah diunggah manual lewat menu Documents
 *     (nama + ukuran cocok, belum punya pemilik) → diadopsi/digabung;
 *  4. selain itu baru dibuat.
 */
class DocumentRegistry
{
    /** doc_type untuk tiap pemilik file modul. */
    public const OWNER_TYPES = [
        Survey::class => 'survey',
        SizingProject::class => 'sizing',
        Instalasi::class => 'instalasi',
    ];

    /** Label sumber file untuk badge di menu dokumen. */
    public const TYPE_LABELS = [
        'survey' => 'Survey',
        'sizing' => 'Sizing',
        'instalasi' => 'Instalasi',
    ];

    /** Nama kategori dokumen bawaan tiap tipe modul. */
    public const CATEGORY_NAMES = [
        'survey' => 'Report Survey',
        'sizing' => 'Sizing',
        'instalasi' => 'Report Instalasi',
    ];

    /**
     * Samakan daftar dokumen dengan file yang dimiliki modul saat ini.
     * Idempoten: aman dipanggil berulang (upload, ganti file, backfill).
     */
    public static function sync(Model $owner): void
    {
        $type = self::OWNER_TYPES[$owner::class] ?? null;
        if (! $type) {
            return;
        }

        $paths = [];

        foreach (self::filesOf($owner) as $file) {
            $document = self::register($owner, $type, $file['path'], $file['name']);
            if ($document) {
                $paths[] = $document->file_path;
            }
        }

        // Baris sisa (file sudah diganti/dihapus di menu modulnya) ikut dibuang
        // supaya dokumen tidak menggantung di menu lain. Hanya dokumen AKTIF:
        // yang sedang di Trash dibiarkan sampai masa simpannya habis.
        ProjectDocument::query()
            ->where('documentable_type', $owner::class)
            ->where('documentable_id', $owner->getKey())
            ->whereNotIn('file_path', $paths ?: [''])
            ->get()
            ->each(function (ProjectDocument $document) {
                $document->forceDelete();
            });
    }

    /**
     * Cabut semua dokumen milik modul (record-nya dihapus). File tidak
     * disentuh — penghapusan file tetap urusan modul pemilik.
     */
    public static function forget(Model $owner): void
    {
        ProjectDocument::withTrashed()
            ->where('documentable_type', $owner::class)
            ->where('documentable_id', $owner->getKey())
            ->get()
            ->each(function (ProjectDocument $document) {
                $document->forceDelete();
            });
    }

    /**
     * Daftar file yang dimiliki record modul.
     *
     * @return array<int, array{path: string, name: string}>
     */
    public static function filesOf(Model $owner): array
    {
        $single = function (?string $path, ?string $name): array {
            if (blank($path)) {
                return [];
            }

            return [[
                'path' => $path,
                'name' => filled($name) ? $name : basename($path),
            ]];
        };

        if ($owner instanceof Survey) {
            return $single($owner->report_file, $owner->report_file_name);
        }

        if ($owner instanceof Instalasi) {
            return $single($owner->report_file, $owner->report_file_name);
        }

        if ($owner instanceof SizingProject) {
            return collect($owner->attachments ?? [])
                ->map(function ($attachment) {
                    $path = is_array($attachment)
                        ? ($attachment['file_path'] ?? $attachment['path'] ?? null)
                        : $attachment;
                    $name = is_array($attachment)
                        ? ($attachment['file_name'] ?? $attachment['name'] ?? null)
                        : null;

                    return blank($path) ? null : [
                        'path' => $path,
                        'name' => filled($name) ? $name : basename($path),
                    ];
                })
                ->filter()
                ->values()
                ->all();
        }

        return [];
    }

    /**
     * Catat satu file modul sebagai dokumen (lihat aturan anti-duplikat).
     */
    public static function register(Model $owner, string $type, string $path, string $name): ?ProjectDocument
    {
        if (blank($path)) {
            return null;
        }

        $disk = Storage::disk('private');
        $exists = $disk->exists($path);
        $size = $exists ? ($disk->size($path) ?: null) : null;
        $mime = null;
        if ($exists) {
            try {
                $mime = $disk->mimeType($path) ?: null;
            } catch (\Throwable $e) {
                $mime = null;
            }
        }

        $projectId = self::projectIdOf($owner);
        $customerId = self::customerIdOf($owner);

        // 1 + 2: baris milik pemilik ini (path sama, atau laporan tunggal).
        $owned = ProjectDocument::withTrashed()
            ->where('documentable_type', $owner::class)
            ->where('documentable_id', $owner->getKey());

        // 1: path sama. Dokumen yang sedang di Trash JANGAN di-restore
        // otomatis oleh sync — biar tetap di Trash sampai dipulihkan manual.
        $document = (clone $owned)
            ->where('file_path', $path)
            ->orderByRaw('case when deleted_at is null then 0 else 1 end')
            ->first();

        if ($document && $document->trashed()) {
            return $document;
        }

        // 2: pemilik satu-file (laporan survey/instalasi) mengganti laporan
        // -> perbarui baris yang AKTIF, bukan baris yang sedang di Trash.
        $document ??= (clone $owned)
            ->whereNull('deleted_at')
            ->where('doc_type', $type)
            ->where('project_id', $projectId)
            ->where('customer_id', $customerId)
            ->first();

        // 3: dokumen yang sama sudah diunggah manual lewat menu Documents.
        $document ??= ProjectDocument::query()
            ->whereNull('documentable_id')
            ->whereNull('doc_type')
            ->where('project_id', $projectId)
            ->where('customer_id', $customerId)
            ->where('file_name', $name)
            ->where(function ($query) use ($size) {
                // Ukuran file = penanda "file yang sama"; ukuran lama yang NULL
                // tetap bisa digabung lewat kecocokan nama.
                $query->whereNull('file_size')
                    ->orWhere('file_size', $size);
            })
            ->orderByRaw('case when file_size = ? then 0 else 1 end', [$size])
            ->first();

        $document ??= new ProjectDocument();

        $document->forceFill([
            'documentable_type' => $owner::class,
            'documentable_id' => $owner->getKey(),
            'doc_type' => $type,
            'project_id' => $projectId,
            'customer_id' => $customerId,
            'document_category_id' => $document->document_category_id ?: self::categoryId($type),
            'file_name' => $name,
            'file_path' => $path,
            'file_size' => $size ?? $document->file_size,
            'mime_type' => $mime ?? $document->mime_type,
            'uploaded_by' => $document->uploaded_by ?: auth()->id(),
        ])->save();

        return $document;
    }

    /**
     * Kategori dokumen bawaan tiap tipe modul (Report Survey, Sizing, dst).
     */
    public static function categoryId(string $type): ?int
    {
        $name = self::CATEGORY_NAMES[$type] ?? null;
        if (blank($name)) {
            return null;
        }

        return DocumentCategory::firstOrCreate(['name' => $name])->id;
    }

    private static function projectIdOf(Model $owner): ?int
    {
        return filled($owner->project_id ?? null) ? (int) $owner->project_id : null;
    }

    private static function customerIdOf(Model $owner): ?int
    {
        if (filled($owner->customer_id ?? null)) {
            return (int) $owner->customer_id;
        }

        $projectId = self::projectIdOf($owner);
        if (! $projectId) {
            return null;
        }

        return (int) Project::query()->whereKey($projectId)->value('customer_id');
    }
}
