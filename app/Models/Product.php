<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    public const STATUS_REVIEW = 'review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'brand', 'name', 'model', 'part_number', 'sku', 'category', 'description', 'status',
        'unit', 'default_price', 'cost_price', 'has_serial_number', 'is_active',
    ];

    protected $casts = [
        'default_price' => 'float',
        'cost_price' => 'float',
        'has_serial_number' => 'boolean',
        'is_active' => 'boolean',
    ];

    /** Kategori untuk picker BOQ proposal (kolom category bebas, ini opsinya). */
    public const SALE_CATEGORIES = ['barang', 'jasa', 'software'];

    /** Pemasok pengadaan (boleh lebih dari satu; is_primary = preferensi). */
    public function suppliers()
    {
        return $this->belongsToMany(Supplier::class, 'product_suppliers')
            ->withPivot(['is_primary', 'supplier_part_number', 'last_cost_price'])
            ->withTimestamps();
    }

    public function primarySupplier()
    {
        return $this->suppliers()->wherePivot('is_primary', true)->first();
    }

    public function sources()
    {
        return $this->hasMany(ProductSource::class);
    }

    public function documents()
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    public function publishedDocuments()
    {
        return $this->hasMany(KnowledgeDocument::class)->where('status', KnowledgeDocument::STATUS_PUBLISHED);
    }

    public function proposalItems()
    {
        return $this->hasMany(ProposalItem::class);
    }

    /** Produk aktif = boleh dipilih di picker BOQ (independen dari status review KB). */
    public function scopeSaleable($query)
    {
        return $query->where('is_active', true);
    }

    public static function saleCategoryLabel(string $category): string
    {
        return match ($category) {
            'barang' => __('Barang'),
            'jasa' => __('Services'),
            'software' => __('Software'),
            default => $category,
        };
    }

    /**
     * Owner tampilan: "Brand Name (Model)".
     */
    public function displayName(): string
    {
        return trim(collect([$this->brand, $this->name, $this->model])->filter()->join(' '));
    }

    /**
     * Identitas cukup jelas untuk auto-publish? Nama wajib + (model atau SKU).
     */
    public function hasClearIdentity(): bool
    {
        return filled($this->name) && (filled($this->model) || filled($this->sku));
    }
}
