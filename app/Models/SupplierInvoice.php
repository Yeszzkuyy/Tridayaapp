<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Invoice dari Supplier (AP) — terpisah dari `invoices` milik pelanggan (AR).
 */
class SupplierInvoice extends Model
{
    use SoftDeletes;

    public const STATUSES = ['unpaid', 'partial', 'paid', 'cancelled'];
    public const TYPES = ['dp', 'partial', 'full'];

    protected $fillable = [
        'internal_ref_number', 'supplier_invoice_number', 'supplier_id',
        'supplier_purchase_order_id', 'goods_receipt_id', 'project_id',
        'invoice_type', 'amount', 'paid_amount', 'status',
        'issue_date', 'due_date', 'invoice_file_path', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_amount' => 'float',
        'issue_date' => 'date',
        'due_date' => 'date',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(SupplierPurchaseOrder::class, 'supplier_purchase_order_id');
    }

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function paidAmount(): float
    {
        if (isset($this->attributes['payments_sum_amount'])) {
            return (float) $this->attributes['payments_sum_amount'];
        }

        return (float) ($this->relationLoaded('payments')
            ? $this->payments->sum('amount')
            : $this->payments()->sum('amount'));
    }

    public function remainingAmount(): float
    {
        return max(0, round((float) $this->amount - $this->paidAmount(), 2));
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'unpaid' => __('Unpaid'),
            'partial' => __('Partial'),
            'paid' => __('Paid'),
            'cancelled' => __('Cancelled'),
            default => ucfirst($status),
        };
    }

    public static function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            'unpaid' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'partial' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
            'paid' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            default => 'bg-slate-200 text-slate-700',
        };
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'dp' => __('Down Payment'),
            'partial' => __('Partial'),
            'full' => __('Full'),
            default => ucfirst($type),
        };
    }
}
