<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WorkType extends Model
{
    protected $fillable = [
        'name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Pilihan untuk form project: hanya Work Type aktif, tetapi Work Type
     * yang sedang dipakai project lama tetap boleh tampil walau nonaktif.
     */
    public function scopeSelectable(Builder $query, ?int $includeInactiveId = null): Builder
    {
        return $query->where(function (Builder $query) use ($includeInactiveId) {
            $query->where('is_active', true);

            if ($includeInactiveId !== null) {
                $query->orWhereKey($includeInactiveId);
            }
        });
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
