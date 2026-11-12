<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu kali harga produk dicatat/diubah.
 * Harga lama tidak pernah ditimpa supaya sales dapat membandingkan
 * penawaran dari beberapa supplier.
 */
class ProductPriceLog extends Model
{
    protected $fillable = [
        'product_id',
        'supplier_id',
        'price',
        'price_type',
        'currency',
        'stock_status',
        'stock_eta',
        'warranty_info',
        'price_request_id',
        'estimator_request_item_id',
        'updated_by',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function priceRequest()
    {
        return $this->belongsTo(PriceRequest::class);
    }

    public function estimatorRequestItem()
    {
        return $this->belongsTo(EstimatorRequestItem::class);
    }
}
