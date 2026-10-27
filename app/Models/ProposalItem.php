<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalItem extends Model
{
    protected $fillable = [
        'proposal_id',
        'product_id',
        'description',
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

    public static function subtotalFor(float $quantity, float $unitPrice, float $discount): float
    {
        return max(0, $quantity * $unitPrice - max(0, $discount));
    }
}
