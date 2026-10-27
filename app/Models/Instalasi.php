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
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'checklist' => 'array',
        'documentation' => 'array',
        'attachments' => 'array',
    ];

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
