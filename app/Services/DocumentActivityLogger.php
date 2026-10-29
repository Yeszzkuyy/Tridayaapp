<?php

namespace App\Services;

use App\Models\DocumentActivity;
use App\Models\ProjectDocument;

/**
 * Catat aktivitas hapus/restore dokumen (aktor, waktu, dokumen).
 * Dipanggil dari controller dan pembersih otomatis.
 */
class DocumentActivityLogger
{
    public static function log(ProjectDocument $document, string $action, array $extraMeta = []): void
    {
        DocumentActivity::create([
            'document_id' => $document->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'file_name' => $document->file_name,
            'doc_type' => $document->doc_type,
            'customer_id' => $document->customer_id,
            'project_id' => $document->project_id,
            'meta' => array_merge([
                'source' => $document->doc_type ?: 'project',
                'file_path' => $document->file_path,
                'deleted_at' => $document->deleted_at?->toDateTimeString(),
            ], $extraMeta),
        ]);
    }
}
