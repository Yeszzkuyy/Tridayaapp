<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Request Harga: sales mengirim file Quotation + customer (PT) + catatan,
 * tim engineer membalas dengan file hasil hitungan.
 * Terpisah dari TechnicalRequest (Request Tim Engineer).
 */
class PriceRequest extends Model
{
    use SoftDeletes;

    public const STATUSES = ['waiting_lead', 'in_progress', 'completed', 'rejected', 'cancelled'];

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

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'waiting_lead' => __('Waiting'),
            'in_progress' => __('In Progress'),
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
