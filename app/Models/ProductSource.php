<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSource extends Model
{
    public const TYPE_PRODUCT_PAGE = 'product_page';
    public const TYPE_DATASHEET = 'datasheet';
    public const TYPE_DOCUMENTATION = 'documentation';
    public const TYPE_CATALOG = 'catalog';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_NEEDS_REVIEW = 'needs_review';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'product_id', 'url', 'source_type', 'status', 'error',
        'last_fetched_at', 'content_hash', 'batch_id',
    ];

    protected $casts = [
        'last_fetched_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function documents()
    {
        return $this->hasMany(KnowledgeDocument::class, 'source_id');
    }

    public function latestDocument()
    {
        return $this->hasOne(KnowledgeDocument::class, 'source_id')->latestOfMany('version');
    }
}
