<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'discount' => 'float',
        'subtotal' => 'float',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public static function subtotalFor(float $quantity, float $unitPrice, float $discount = 0): float
    {
        return round(max(0, ($quantity * $unitPrice) - $discount), 2);
    }
}
