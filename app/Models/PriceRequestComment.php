<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PriceRequestComment extends Model
{
    public const TYPE_MESSAGE = 'message';
    public const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'price_request_id',
        'user_id',
        'body',
        'type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function priceRequest()
    {
        return $this->belongsTo(PriceRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
