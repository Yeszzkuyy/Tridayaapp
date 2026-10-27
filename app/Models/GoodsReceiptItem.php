<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id', 'supplier_purchase_order_item_id', 'product_id',
        'quantity_received', 'serial_numbers', 'condition_notes',
    ];

    protected $casts = [
        'quantity_received' => 'float',
        'serial_numbers' => 'array',
    ];

    public function receipt()
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(SupplierPurchaseOrderItem::class, 'supplier_purchase_order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
