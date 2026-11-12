<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Request Harga: sales/teknisi mengirim quotation dan assigned engineer
 * mengelola versi hitungan serta diskusi. Satu tabel dipakai berdua
 * (sales + teknisi) sehingga keduanya terhubung.
 * Terpisah dari TechnicalRequest (Request Tim Engineer).
 */
class PriceRequest extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'waiting_lead', 'accepted', 'assigned', 'in_progress', 'waiting_sales_review',
        'revision', 'completed', 'rejected', 'cancelled',
    ];

    protected $fillable = [
        'customer_id',
        'lead_id',
        'project_id',
        'proposal_id',
        'requested_by',
        'quotation_path',
        'notes',
        'status',
        'assigned_to',
        'handled_by',
        'reply_path',
        'reply_notes',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies()
    {
        return $this->hasMany(PriceRequestReply::class)->orderBy('version');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /** Estimator penanggung jawab (kolom baru; assigned_to tetap mesin status). */
    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    /** Harga yang dicatat saat request ini diselesaikan. */
    public function priceLogs()
    {
        return $this->hasMany(ProductPriceLog::class)
            ->where('price_type', 'cost')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function latestPriceLog()
    {
        return $this->hasOne(ProductPriceLog::class)
            ->where('price_type', 'cost')
            ->latestOfMany();
    }

    /** Waktu update harga terakhir, dipakai antrean Sales. */
    public function getLastPriceUpdateAtAttribute(): ?\Illuminate\Support\Carbon
    {
        $dates = array_filter([
            $this->price_logs_max_created_at ?? null,
            $this->replies_max_created_at ?? null,
        ]);

        return $dates ? \Illuminate\Support\Carbon::parse(max($dates)) : null;
    }

    /** Label status yang ramah untuk antrean Sales/Estimator. */
    public static function queueStatusLabel(?string $status): string
    {
        return match ($status) {
            'waiting_lead', 'accepted', 'assigned', 'in_progress' => __('Waiting for Estimator'),
            'waiting_sales_review' => __('Price Received'),
            'revision' => __('Revision'),
            'completed' => __('Completed'),
            'rejected' => __('Rejected'),
            'cancelled' => __('Cancelled'),
            default => __('-'),
        };
    }

    public static function queueStatusColor(?string $status): string
    {
        return match ($status) {
            'waiting_lead', 'accepted', 'assigned', 'in_progress' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'waiting_sales_review' => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300',
            'revision' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
            'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'rejected' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            'cancelled' => 'bg-slate-200 text-slate-500 dark:bg-slate-600 dark:text-slate-300',
            default => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
        };
    }

    public function comments()
    {
        return $this->hasMany(PriceRequestComment::class)->orderBy('id');
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'waiting_lead' => __('Waiting'),
            'accepted' => __('Accepted'),
            'assigned' => __('Assigned'),
            'in_progress' => __('In Progress'),
            'waiting_sales_review' => __('Waiting for Sales Review'),
            'revision' => __('Revision Requested'),
            'completed' => __('Completed'),
            'rejected' => __('Rejected'),
            'cancelled' => __('Cancelled'),
            default => __('-'),
        };
    }

    /** True bila request sudah tidak bisa diubah lagi. */
    public function isClosed(): bool
    {
        return in_array($this->status, ['completed', 'rejected', 'cancelled'], true);
    }
}
