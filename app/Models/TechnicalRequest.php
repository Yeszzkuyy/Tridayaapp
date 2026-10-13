<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicalRequest extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'survey', 'estimate', 'installation', 'troubleshooting', 'consultation', 'other',
    ];

    public const PRIORITIES = ['normal', 'urgent'];

    public const STATUSES = [
        'waiting_lead', 'accepted', 'rejected', 'assigned', 'in_progress', 'completed', 'cancelled',
    ];

    protected $fillable = [
        'lead_id',
        'requested_by',
        'request_type',
        'title',
        'requirement',
        'problem_description',
        'scope',
        'priority',
        'target_date',
        'attachment_path',
        'notes',
        'status',
        'assigned_technician_id',
        'technical_result',
        'completed_at',
    ];

    protected $casts = [
        'target_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    /**
     * Survey yang lahir dari request ini (maksimal satu — dijamin unik di DB).
     */
    public function survey()
    {
        return $this->hasOne(Survey::class);
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'survey' => __('Survey'),
            'estimate' => __('Estimasi'),
            'installation' => __('Instalasi'),
            'troubleshooting' => __('Troubleshooting'),
            'consultation' => __('Technical Consultation'),
            default => __('Other'),
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'waiting_lead' => __('Waiting Lead Teknisi'),
            'accepted' => __('Accepted'),
            'rejected' => __('Rejected'),
            'assigned' => __('Assigned'),
            'in_progress' => __('In Progress'),
            'completed' => __('Completed'),
            'cancelled' => __('Cancelled'),
            default => $status,
        };
    }
}
