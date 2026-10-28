<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instalasi extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id', 'location', 'technician_pic', 'schedule_date',
        'job_status', 'checklist', 'documentation', 'installation_report',
        'notes', 'attachments', 'status',
        'report_file', 'report_file_name',
        'completed_at', 'completed_by',
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'checklist' => 'array',
        'documentation' => 'array',
        'attachments' => 'array',
        'completed_at' => 'datetime',
    ];

    /** Teknisi yang menandai pekerjaan selesai secara teknis. */
    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isTechnicallyComplete(): bool
    {
        return $this->status === 'completed';
    }

    public function hasReport(): bool
    {
        return filled($this->report_file);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Baris dokumen untuk laporan instalasi (file-nya tetap satu).
     */
    public function documents()
    {
        return $this->morphMany(ProjectDocument::class, 'documentable');
    }
}
