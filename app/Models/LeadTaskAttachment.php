<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu versi file quotation yang diunggah Inside Sales untuk sebuah task.
 * Versi lama tidak dihapus supaya riwayat revisi tetap bisa diunduh.
 */
class LeadTaskAttachment extends Model
{
    protected $fillable = [
        'lead_task_id',
        'proposal_id',
        'file_path',
        'original_name',
        'mime',
        'size',
        'uploaded_by',
        'accepted_by',
        'accepted_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'accepted_at' => 'datetime',
    ];

    public function task()
    {
        return $this->belongsTo(LeadTask::class, 'lead_task_id');
    }

    /** Quotation (proposal) hasil accept file ini, kalau sudah diterima. */
    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function accepter()
    {
        return $this->belongsTo(User::class, 'accepted_by')->withTrashed();
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
