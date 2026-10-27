<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicalRequest extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'survey', 'estimate', 'installation', 'troubleshooting', 'consultation', 'demo', 'poc', 'other',
    ];

    /**
     * Tipe yang ditawarkan di form Request Tim Engineer. 'estimate'
     * dikeluarkan karena sudah punya sistem sendiri (Request Harga).
     */
    public const FORM_TYPES = [
        'survey', 'installation', 'troubleshooting', 'consultation', 'demo', 'poc', 'other',
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
        'location',
        'purpose',
        'pic_contact_name',
        'pic_contact_phone',
        'requirement',
        'problem_description',
        'scope',
        'priority',
        'target_date',
        'target_time',
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
            'estimate' => __('Price Estimation'),
            'installation' => __('Installation'),
            'troubleshooting' => __('Troubleshooting'),
            'consultation' => __('Technical Consultation'),
            'demo' => __('Product Demo'),
            'poc' => __('Product POC'),
            default => __('Other'),
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'waiting_lead' => __('Waiting for Lead Technician'),
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
