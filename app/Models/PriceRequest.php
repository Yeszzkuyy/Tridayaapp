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
        'requested_by',
        'quotation_path',
        'notes',
        'status',
        'assigned_to',
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
