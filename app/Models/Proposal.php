<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proposal extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'draft', 'pending_technical', 'ready', 'sent', 'viewed', 'revision', 'accepted', 'rejected', 'expired', 'cancelled',
    ];

    protected $fillable = [
        'lead_id',
        'proposal_number',
        'created_by',
        'proposal_date',
        'valid_until',
        'customer',
        'subtotal',
        'discount',
        'tax',
        'grand_total',
        'notes',
        'po_document_path',
        'status',
        'sent_at',
        'sent_by',
    ];

    protected $casts = [
        'proposal_date' => 'date',
        'valid_until' => 'date',
        'sent_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function items()
    {
        return $this->hasMany(ProposalItem::class);
    }

    public function project()
    {
        return $this->hasOne(Project::class);
    }

    public function recalculate(): void
    {
        $subtotal = (float) $this->items()->sum('subtotal');
        $discount = min((float) ($this->discount ?? 0), $subtotal);
        $tax = max(0, (float) ($this->tax ?? 0));
        $this->forceFill([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'grand_total' => $subtotal - $discount + $tax,
        ])->save();
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => __('Draft'),
            'pending_technical' => __('Pending Technical'),
            'ready' => __('Ready'),
            'sent' => __('Sent'),
            'viewed' => __('Viewed'),
            'revision' => __('Revision Requested'),
            // Keputusan PRD: di UI/KPI, accepted tampil sebagai "WON".
            'accepted' => __('Won'),
            'rejected' => __('Rejected'),
            'expired' => __('Expired'),
            'cancelled' => __('Cancelled'),
            default => $status,
        };
    }

    /** Warna badge per status (satu sumber untuk index/show/lead). */
    public static function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            'draft' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            'pending_technical' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
            'ready' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'sent' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
            'viewed' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300',
            'revision' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
            'accepted' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            'expired' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
            'cancelled' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            default => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
        };
    }
}
