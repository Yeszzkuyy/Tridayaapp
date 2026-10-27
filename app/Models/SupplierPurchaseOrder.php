<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pesanan 3DY KEPADA PEMASOK (Supplier Purchase Order).
 *
 * Berbeda dari `purchase_orders` (PO sisi pelanggan / customer PO).
 * Satu quotation boleh menghasilkan banyak Supplier PO (mis. komponen
 * dikirim pemasok berbeda).
 */
class SupplierPurchaseOrder extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'ordered', 'partially_received', 'received', 'cancelled'];

    protected $fillable = [
        'po_number', 'supplier_id', 'proposal_id', 'project_id',
        'issue_date', 'expected_delivery_date', 'status', 'notes',
        'subtotal', 'discount', 'tax', 'grand_total',
        'idempotency_key', 'created_by',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expected_delivery_date' => 'date',
        'subtotal' => 'float',
        'discount' => 'float',
        'tax' => 'float',
        'grand_total' => 'float',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function items()
    {
        return $this->hasMany(SupplierPurchaseOrderItem::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function supplierInvoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Hitung ulang total dari item — satu sumber perhitungan. */
    public function recalculate(): void
    {
        $items = $this->items()->get();
        $subtotal = round((float) $items->sum('subtotal'), 2);
        $discount = round(min((float) ($this->discount ?? 0), $subtotal), 2);
        $tax = round(max(0, (float) ($this->tax ?? 0)), 2);

        $this->forceFill([
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'grand_total' => round($subtotal - $discount + $tax, 2),
        ])->save();
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => __('Draft'),
            'ordered' => __('Ordered'),
            'partially_received' => __('Partially Received'),
            'received' => __('Received'),
            'cancelled' => __('Cancelled'),
            default => ucfirst($status),
        };
    }

    public static function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            'draft' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            'ordered' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
            'partially_received' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            'received' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            default => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
        };
    }

    /** Total kuantitas dipesan vs diterima (dasar status Tahap 3). */
    public function quantitySummary(): array
    {
        $items = $this->items;

        return [
            'ordered' => (float) $items->sum('quantity_ordered'),
            'received' => (float) $items->sum('quantity_received'),
        ];
    }
}
