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
        'brand', 'name', 'model', 'sku', 'category', 'description', 'status',
        'unit', 'default_price', 'is_active',
    ];

    protected $casts = [
        'default_price' => 'float',
        'is_active' => 'boolean',
    ];

    /** Kategori untuk picker BOQ proposal (kolom category bebas, ini opsinya). */
    public const SALE_CATEGORIES = ['barang', 'jasa', 'software'];

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
