<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadTaskItem extends Model
{
    protected $fillable = [
        'lead_task_id',
        'product_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function leadTask()
    {
        return $this->belongsTo(LeadTask::class);
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
