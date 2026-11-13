<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EstimatorRequest extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'submitted', 'in_progress', 'answered', 'revision', 'completed', 'rejected', 'cancelled',
    ];

    public const OPEN_STATUSES = ['submitted', 'in_progress', 'answered', 'revision'];

    protected $fillable = [
        'customer_id',
        'lead_id',
        'project_id',
        'proposal_id',
        'requested_by',
        'handled_by',
        'status',
        'notes',
        'revision_reason',
        'revision_requested_at',
        'attachment_path',
        'attachment_name',
        'attachment_size',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'revision_requested_at' => 'datetime',
        'attachment_size' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function items()
    {
        return $this->hasMany(EstimatorRequestItem::class)->orderBy('id');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', self::OPEN_STATUSES);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'submitted' => __('Waiting for Estimator'),
            'in_progress' => __('In Progress'),
            'answered' => __('Price Received'),
            'revision' => __('Revision'),
            'completed' => __('Completed'),
            'rejected' => __('Rejected'),
            'cancelled' => __('Cancelled'),
            default => __('-'),
        };
    }

    public static function statusColor(?string $status): string
    {
        return match ($status) {
            'submitted' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'in_progress' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'answered' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300',
            'revision' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            'cancelled' => 'bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300',
            default => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        };
    }
}
