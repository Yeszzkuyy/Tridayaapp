<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Poc extends Model
{
    use SoftDeletes;

    public const TYPES = ['poc', 'demo'];

    public const STATUSES = ['scheduled', 'done', 'cancelled'];

    protected $fillable = [
        'customer_id',
        'lead_id',
        'type',
        'scheduled_date',
        'location',
        'status',
        'result_notes',
        'created_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
    ];

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            'poc' => 'POC',
            'demo' => __('Demo'),
            default => '-',
        };
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'scheduled' => __('Terjadwal'),
            'done' => __('Selesai'),
            'cancelled' => __('Batal'),
            default => '-',
        };
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
