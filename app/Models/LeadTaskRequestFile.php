<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Lampiran pendukung dari Sales untuk request Inside Sales.
 * Berbeda dari LeadTaskAttachment (file quotation dari Inside Sales).
 */
class LeadTaskRequestFile extends Model
{
    protected $fillable = [
        'lead_task_id',
        'file_path',
        'original_name',
        'mime',
        'size',
        'uploaded_by',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function task()
    {
        return $this->belongsTo(LeadTask::class, 'lead_task_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }

    /** Ukuran file dalam bentuk manusiawi: 812 KB / 1.4 MB. */
    public function humanSize(): string
    {
        $bytes = (int) $this->size;

        if ($bytes >= 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1).' MB';
        }

        return max(1, (int) round($bytes / 1024)).' KB';
    }
}
