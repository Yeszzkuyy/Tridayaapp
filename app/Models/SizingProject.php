<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SizingProject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id', 'technician_user_id', 'sales_pic', 'customer_needs', 'recommendation',
        'specifications', 'quantity', 'topology', 'technical_notes',
        'attachments', 'status',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'attachments' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    /**
     * Baris dokumen untuk lampiran hasil sizing.
     */
    public function documents()
    {
        return $this->morphMany(ProjectDocument::class, 'documentable');
    }
}
