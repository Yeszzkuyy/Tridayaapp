<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectStatus extends Model
{
    use SoftDeletes;

    /**
     * Palet warna badge per nama status, satu sumber untuk dashboard teknisi
     * dan daftar project. Nilai = kunci warna komponen <x-status-badge>.
     */
    public const BADGE_COLORS = [
        'Open' => 'blue',
        'On Progress' => 'yellow',
        'Pending' => 'orange',
        'Hold' => 'red',
        'Done' => 'green',
        'Cancelled' => 'slate',
        'Warranty' => 'cyan',
        'Maintenance' => 'purple',
    ];

    public const CODE_OPEN = 'open';
    public const CODE_ON_PROGRESS = 'on_progress';
    public const CODE_PENDING = 'pending';
    public const CODE_HOLD = 'hold';
    public const CODE_DONE = 'done';
    public const CODE_CANCELLED = 'cancelled';
    public const CODE_WARRANTY = 'warranty';
    public const CODE_MAINTENANCE = 'maintenance';

    protected $fillable = [
        'name',
        'code',
        'is_system',
        'color',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function isSystem(): bool
    {
        return (bool) $this->is_system;
    }

    // Relasi ke Project
    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    // Scope untuk default status
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Scope untuk status aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}