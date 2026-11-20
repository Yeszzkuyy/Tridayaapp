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
        'unit', 'default_price', 'cost_price', 'cost_includes_tax', 'has_serial_number', 'is_active',
    ];

    protected $casts = [
        'default_price' => 'float',
        'cost_price' => 'float',
        'cost_includes_tax' => 'boolean',
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

    /** Riwayat harga, terbaru di atas. */
    public function priceLogs()
    {
        return $this->hasMany(ProductPriceLog::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /** Riwayat harga modal saja (bukan harga jual default). */
    public function costLogs()
    {
        return $this->hasMany(ProductPriceLog::class)
            ->where('price_type', 'cost')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function latestPriceLog()
    {
        return $this->hasOne(ProductPriceLog::class)->latestOfMany();
    }

    public function latestCostLog()
    {
        return $this->hasOne(ProductPriceLog::class)
            ->where('price_type', 'cost')
            ->latestOfMany();
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

    /** Harga modal efektif: kolom produk, fallback ke log terakhir bila perlu. */
    public function effectiveCostPrice(): ?float
    {
        if ($this->cost_price !== null) {
            return (float) $this->cost_price;
        }

        if ($this->relationLoaded('latestCostLog') && $this->latestCostLog) {
            return (float) $this->latestCostLog->price;
        }

        return null;
    }

    public function getMarginAmountAttribute(): ?float
    {
        $cost = $this->effectiveCostPrice();
        $selling = $this->default_price === null ? null : (float) $this->default_price;

        if ($cost === null || $selling === null) {
            return null;
        }

        return $selling - $cost;
    }

    public function getMarginPercentageAttribute(): ?float
    {
        $cost = $this->effectiveCostPrice();
        $selling = $this->default_price === null ? 0.0 : (float) $this->default_price;

        if ($cost === null || $selling <= 0) {
            return null;
        }

        return (($selling - $cost) / $selling) * 100;
    }

    public function getMarginStatusAttribute(): ?string
    {
        $margin = $this->margin_percentage;

        if ($margin === null) {
            return null;
        }

        if ($margin < 0) {
            return 'loss';
        }

        return $margin <= 10 ? 'low' : 'healthy';
    }

    /**
     * Perbandingan harga modal terakhir vs sebelumnya.
     * Arah "up" berarti harga modal naik (buruk untuk margin).
     */
    public function getPriceTrendAttribute(): ?array
    {
        $logs = $this->relationLoaded('costLogs')
            ? $this->costLogs
            : $this->costLogs()->limit(2)->get();

        if ($logs->count() < 2) {
            return null;
        }

        $current = (float) $logs[0]->price;
        $previous = (float) $logs[1]->price;

        if ($previous <= 0) {
            return null;
        }

        $percentage = (($current - $previous) / $previous) * 100;

        if (abs($percentage) < 0.05) {
            return null;
        }

        return [
            'percentage' => $percentage,
            'direction' => $percentage > 0 ? 'up' : 'down',
        ];
    }

    /** Harga dianggap aktif bila log terakhir masih berlaku dan bukan margin 0. */
    public function scopePriceActive($query): void
    {
        $today = now()->toDateString();

        $query->whereNotNull('cost_price')
            ->where('default_price', '>', 0)
            ->whereColumn('default_price', '!=', 'cost_price')
            ->whereHas('latestCostLog', fn ($log) => $log
                ->whereNotNull('valid_until')
                ->whereDate('valid_until', '>=', $today));
    }

    /** Belum ada harga, harga kedaluwarsa, atau harga sama dengan harga jual. */
    public function scopeNeedsRecheck($query): void
    {
        $today = now()->toDateString();

        $query->where(function ($outer) use ($today): void {
            $outer->whereNull('cost_price')
                ->orWhere('default_price', '<=', 0)
                ->orWhereColumn('default_price', '=', 'cost_price')
                ->orWhereDoesntHave('latestCostLog')
                ->orWhereHas('latestCostLog', fn ($log) => $log->where(
                    fn ($inner) => $inner
                        ->whereNull('valid_until')
                        ->orWhereDate('valid_until', '<', $today)
                ));
        });
    }

    public function scopeNegativeMargin($query): void
    {
        $query->whereNotNull('cost_price')
            ->whereColumn('cost_price', '>', 'default_price');
    }

    public function scopeExpiringSoon($query): void
    {
        $today = now()->toDateString();
        $until = now()->addDays(7)->toDateString();

        $query->whereHas('latestCostLog', fn ($log) => $log
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '>=', $today)
            ->whereDate('valid_until', '<=', $until));
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
