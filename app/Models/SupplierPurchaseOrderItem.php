<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierPurchaseOrderItem extends Model
{
    protected $fillable = [
        'supplier_purchase_order_id', 'proposal_item_id', 'product_id',
        'item_name', 'part_number', 'description', 'unit',
        'quantity_ordered', 'quantity_received', 'unit_price', 'subtotal',
    ];

    protected $casts = [
        'quantity_ordered' => 'float',
        'quantity_received' => 'float',
        'unit_price' => 'float',
        'subtotal' => 'float',
    ];

    public function purchaseOrder()
    {
        return $this->belongsTo(SupplierPurchaseOrder::class, 'supplier_purchase_order_id');
    }

    public function proposalItem()
    {
        return $this->belongsTo(ProposalItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function quantityRemaining(): float
    {
        return round((float) $this->quantity_ordered - (float) $this->quantity_received, 2);
    }
}
