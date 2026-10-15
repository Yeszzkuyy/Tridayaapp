<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesSchedule extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'meeting', 'call', 'visit', 'presentation', 'follow_up', 'quotation', 'internal', 'other',
    ];

    public const STATUSES = ['scheduled', 'completed', 'cancelled', 'rescheduled'];

    protected $fillable = [
        'lead_id',
        'assigned_to',
        'created_by',
        'title',
        'type',
        'start_at',
        'end_at',
        'location',
        'description',
        'status',
        'reminder_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'reminder_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'meeting' => __('Meeting'),
            'call' => __('Call'),
            'visit' => __('Customer Visit'),
            'presentation' => __('Presentation'),
            'follow_up' => __('Follow-up'),
            'quotation' => __('Quotation'),
            'internal' => __('Internal Meeting'),
            default => __('Other'),
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'scheduled' => __('Scheduled'),
            'completed' => __('Completed'),
            'cancelled' => __('Cancelled'),
            'rescheduled' => __('Rescheduled'),
            default => $status,
        };
    }
}
