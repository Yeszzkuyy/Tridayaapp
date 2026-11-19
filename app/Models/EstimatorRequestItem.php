<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstimatorRequestItem extends Model
{
    public const STATUSES = ['pending', 'answered', 'revision', 'accepted'];

    protected $fillable = [
        'estimator_request_id',
        'product_id',
        'supplier_id',
        'description',
        'quantity',
        'unit',
        'price',
        'stock_status',
        'stock_eta',
        'warranty_info',
        'valid_until',
        'notes',
        'status',
        'answered_by',
        'answered_at',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price' => 'decimal:2',
        'valid_until' => 'date',
        'answered_at' => 'datetime',
    ];

    public function estimatorRequest()
    {
        return $this->belongsTo(EstimatorRequest::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function answeredBy()
    {
        return $this->belongsTo(User::class, 'answered_by');
    }
}
