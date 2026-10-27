<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentActivity extends Model
{
    protected $fillable = [
        'document_id',
        'user_id',
        'action',
        'file_name',
        'doc_type',
        'customer_id',
        'project_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
