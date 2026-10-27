<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsDeliveryItem extends Model
{
    protected $fillable = [
        'goods_delivery_id', 'product_id', 'supplier_purchase_order_item_id',
        'item_name', 'part_number', 'unit', 'quantity', 'serial_numbers',
    ];

    protected $casts = [
        'quantity' => 'float',
        'serial_numbers' => 'array',
    ];

    public function delivery()
    {
        return $this->belongsTo(GoodsDelivery::class, 'goods_delivery_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrderItem()
    {
        return $this->belongsTo(SupplierPurchaseOrderItem::class, 'supplier_purchase_order_item_id');
    }
}
