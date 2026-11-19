<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalItem extends Model
{
    protected $fillable = [
        'proposal_id',
        'product_id',
        'product_price_log_id',
        'description',
        'scope_of_supply',
        'lead_time',
        'stock_status',
        'stock_eta',
        'warranty_info',
        'section',
        'quantity',
        'unit',
        'unit_price',
        'unit_cost',
        'discount',
        'subtotal',
    ];

    public function proposal()
    {
        return $this->belongsTo(Proposal::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function priceLog()
    {
        return $this->belongsTo(ProductPriceLog::class, 'product_price_log_id');
    }

    public static function subtotalFor(float $quantity, float $unitPrice, float $discount): float
    {
        return max(0, $quantity * $unitPrice - max(0, $discount));
    }
}
