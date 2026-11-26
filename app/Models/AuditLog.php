<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'causer_id',
        'causer_name',
        'causer_email',
        'module',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'old_values',
        'new_values',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException(__('Audit log is append-only and cannot be changed.'));
        });

        static::deleting(function (): void {
            throw new RuntimeException(__('Audit log is append-only and cannot be deleted.'));
        });
    }

    public function causer()
    {
        return $this->belongsTo(User::class, 'causer_id')->withTrashed();
    }

    public function subject()
    {
        return $this->morphTo();
    }
}
