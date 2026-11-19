<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Instalasi extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id', 'customer_id', 'sales_user_id', 'technical_request_id',
        'location', 'technician_pic', 'technician_user_id', 'schedule_date',
        'job_status', 'checklist', 'documentation', 'installation_report',
        'notes', 'attachments', 'status',
        'report_file', 'report_file_name',
        'completed_at', 'completed_by',
        'bast_number', 'bast_date', 'bast_document_id',
        'bast_signed_3dy_name', 'bast_signed_3dy_at',
        'bast_signed_customer_name', 'bast_signed_customer_at',
        'bast_ready_at',
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'checklist' => 'array',
        'documentation' => 'array',
        'attachments' => 'array',
        'completed_at' => 'datetime',
        'bast_date' => 'date',
        'bast_signed_3dy_at' => 'datetime',
        'bast_signed_customer_at' => 'datetime',
        'bast_ready_at' => 'datetime',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    public function technicalRequest()
    {
        return $this->belongsTo(TechnicalRequest::class);
    }

    public function bastDocument()
    {
        return $this->belongsTo(ProjectDocument::class, 'bast_document_id');
    }

    /**
     * BAST dianggap siap bila dokumen tersimpan dan sudah ditandatangani
     * kedua pihak (3DY + pelanggan). Sama dengan aturan BAST project.
     */
    public function isBastReady(): bool
    {
        return filled($this->bast_document_id)
            && filled($this->bast_signed_3dy_at)
            && filled($this->bast_signed_customer_at);
    }

    public function refreshBastReady(): bool
    {
        $ready = $this->isBastReady();

        $this->forceFill([
            'bast_ready_at' => $ready ? ($this->bast_ready_at ?? now()) : null,
        ])->save();

        return $ready;
    }

    /**
     * Baris dokumen untuk laporan instalasi (file-nya tetap satu).
     */
    public function documents()
    {
        return $this->morphMany(ProjectDocument::class, 'documentable');
    }
}
