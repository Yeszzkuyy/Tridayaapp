<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeChunk extends Model
{
    protected $fillable = [
        'document_id', 'chunk_index', 'content', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function document()
    {
        return $this->belongsTo(KnowledgeDocument::class, 'document_id');
    }

    /**
     * Cari potongan published berisi kata kunci (FTS bawaan PostgreSQL).
     */
    public static function searchPublished(string $query, int $limit = 8)
    {
        $q = static::query()
            ->join('knowledge_documents', 'knowledge_documents.id', '=', 'knowledge_chunks.document_id')
            ->where('knowledge_documents.status', KnowledgeDocument::STATUS_PUBLISHED);

        if (\Illuminate\Support\Facades\DB::getDriverName() === 'pgsql') {
            $q->whereRaw("to_tsvector('simple', knowledge_chunks.content) @@ plainto_tsquery('simple', ?)", [$query])
                ->orderByRaw("ts_rank(to_tsvector('simple', knowledge_chunks.content), plainto_tsquery('simple', ?)) DESC", [$query]);
        } else {
            $q->where('knowledge_chunks.content', 'like', '%' . $query . '%')
                ->latest('knowledge_chunks.id');
        }

        return $q->limit($limit)->get(['knowledge_chunks.*']);
    }
}
