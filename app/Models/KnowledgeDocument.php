<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeDocument extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'source_id', 'product_id', 'title', 'content',
        'source_url', 'version', 'content_hash', 'status',
    ];

    public function source()
    {
        return $this->belongsTo(ProductSource::class, 'source_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function chunks()
    {
        return $this->hasMany(KnowledgeChunk::class, 'document_id')->orderBy('chunk_index');
    }
}
