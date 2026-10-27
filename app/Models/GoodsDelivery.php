<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Surat Jalan / pengiriman barang keluar ke pelanggan (Tahap 3).
 * Status `delivered` menjadi salah satu syarat closing komersial proyek.
 */
class GoodsDelivery extends Model
{
    use SoftDeletes;

    public const STATUSES = ['prepared', 'shipped', 'delivered'];

    protected $fillable = [
        'delivery_number', 'project_id', 'customer_id', 'delivery_date',
        'recipient_name', 'status', 'notes', 'created_by',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items()
    {
        return $this->hasMany(GoodsDeliveryItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'prepared' => __('Prepared'),
            'shipped' => __('Shipped'),
            'delivered' => __('Delivered'),
            default => ucfirst($status),
        };
    }

    public static function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            'prepared' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            'shipped' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
            'delivered' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            default => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
        };
    }
}
