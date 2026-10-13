<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Survey extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'project_id', 'customer_id', 'sales_user_id', 'sales_request', 'survey_date',
        'location', 'pic', 'survey_data', 'documentation', 'survey_report',
        'report_file', 'report_file_name', 'status', 'notes',
    ];

    /**
     * Status selesai yang diakui aplikasi (lihat validasi controller & badge view).
     */
    public const STATUS_COMPLETED = 'completed';

    /**
     * Apakah survey sudah punya file laporan PDF.
     */
    public function hasReport(): bool
    {
        return filled($this->report_file);
    }

    protected $casts = [
        'survey_date' => 'date',
        'documentation' => 'array',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_user_id');
    }
}
