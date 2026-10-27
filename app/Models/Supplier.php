<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Master pemasok pengadaan (BUKAN `partners` — itu relasi marketing/lead).
 */
class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'contact_person', 'phone', 'email',
        'address', 'notes', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_suppliers')
            ->withPivot(['is_primary', 'supplier_part_number', 'last_cost_price'])
            ->withTimestamps();
    }

    public function purchaseOrders()
    {
        return $this->hasMany(SupplierPurchaseOrder::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function invoices()
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function primaryProducts()
    {
        return $this->belongsToMany(Product::class, 'product_suppliers')
            ->wherePivot('is_primary', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
