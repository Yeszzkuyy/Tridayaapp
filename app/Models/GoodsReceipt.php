<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Dokumen Terima Barang dari Supplier (Tahap 3).
 * Kuantitas baru masuk ke item PO setelah receipt berstatus `verified`.
 */
class GoodsReceipt extends Model
{
    use SoftDeletes;

    public const STATUSES = ['draft', 'verified'];

    protected $fillable = [
        'receipt_number', 'supplier_purchase_order_id', 'supplier_id',
        'project_id', 'received_date', 'supplier_delivery_note_no',
        'received_by', 'status', 'notes', 'attachment_path',
    ];

    protected $casts = [
        'received_date' => 'date',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(SupplierPurchaseOrder::class, 'supplier_purchase_order_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items()
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function supplierInvoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => __('Draft'),
            'verified' => __('Verified'),
            default => ucfirst($status),
        };
    }

    public static function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            'draft' => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
            'verified' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            default => 'bg-slate-200 text-slate-700 dark:bg-slate-600 dark:text-slate-200',
        };
    }
}
