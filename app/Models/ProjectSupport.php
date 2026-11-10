<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // ← Tambahkan

class ProjectSupport extends Model
{
    use SoftDeletes; // ← Tambahkan

    protected $fillable = [
        'project_id',
        'user_id',
        'title',
        'description',
        'status',
        'priority',
        'assigned_to',
        'resolved_at',
        'resolved_by',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function engineer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
