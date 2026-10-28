<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupplierPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'supplier_invoice_id', 'supplier_purchase_order_id', 'amount',
        'payment_date', 'payment_method', 'proof_file', 'notes', 'idempotency_key', 'created_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'payment_date' => 'date',
    ];

    public function invoice()
    {
        return $this->belongsTo(SupplierInvoice::class, 'supplier_invoice_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(SupplierPurchaseOrder::class, 'supplier_purchase_order_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
