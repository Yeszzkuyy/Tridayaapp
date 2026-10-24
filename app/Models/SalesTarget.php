<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesTarget extends Model
{
    protected $fillable = [
        'user_id',
        'period_month',
        'period_year',
        'target_revenue',
        'target_deals',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'period_month' => 'integer',
        'period_year' => 'integer',
        'target_revenue' => 'decimal:2',
        'target_deals' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForPeriod($query, int $month, int $year)
    {
        return $query->where('period_month', $month)->where('period_year', $year);
    }
}
