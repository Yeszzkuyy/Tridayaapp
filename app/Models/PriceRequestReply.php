<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceRequestReply extends Model
{
    protected $fillable = [
        'price_request_id',
        'uploaded_by',
        'version',
        'file_path',
        'notes',
        'is_legacy',
        'accepted_by',
        'accepted_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'is_legacy' => 'boolean',
        'accepted_at' => 'datetime',
    ];

    public function priceRequest()
    {
        return $this->belongsTo(PriceRequest::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }
}
