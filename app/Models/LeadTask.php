<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeadTask extends Model
{
    use SoftDeletes;

    public const STATUSES = ['todo', 'in_progress', 'review', 'done'];

    public const PRIORITIES = ['low', 'medium', 'high'];

    protected $fillable = [
        'lead_id',
        'title',
        'description',
        'assigned_to',
        'due_date',
        'priority',
        'status',
        'created_by',
    ];

    protected $casts = [
        'due_date' => 'date',
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

    public function comments()
    {
        return $this->hasMany(LeadTaskComment::class)->orderBy('id', 'asc');
    }

    /** Riwayat file quotation (versi terbaru di atas). */
    public function attachments()
    {
        return $this->hasMany(LeadTaskAttachment::class)->latest('id');
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'in_progress' => __('In Progress'),
            'review' => __('Review'),
            'done' => __('Done'),
            default => __('Todo'),
        };
    }
}
