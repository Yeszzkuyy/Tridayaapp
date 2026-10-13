<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    protected $table = 'projects';

    protected $fillable = [
        'customer_id',
        'sales_user_id',
        'teknisi_user_id',
        'account_manager_id',
        'work_type_id',
        'project_status_id',
        'pic_engineer', // ← ganti dari pic_engineer_id
        'support_technicians', // ← tambahkan
        'project_name',
        'project_code',
        'quotation_number',
        'progress',
        'start_date',
        'end_date',
        'description',
        'deleted_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'progress' => 'integer',
    ];

    // Relasi ke Customer
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Owner bisnis untuk Trash: ikut owner customer-nya.
     * withTrashed karena customer-nya sendiri bisa sedang di trash.
     */
    public function trashOwnerId(): ?int
    {
        return $this->customer()->withTrashed()->first()?->trashOwnerId();
    }

    // Relasi ke AccountManager
    public function accountManager()
    {
        return $this->belongsTo(AccountManager::class);
    }

    // Relasi ke WorkType
    public function workType()
    {
        return $this->belongsTo(WorkType::class);
    }

    // Relasi ke ProjectStatus (kalau pake)
    public function status()
    {
        return $this->belongsTo(ProjectStatus::class, 'project_status_id');
    }

    // Relasi ke Task
    public function tasks()
    {
        return $this->hasMany(ProjectTask::class);
    }

    // Relasi ke Activity
    public function activities()
    {
        return $this->hasMany(ProjectActivity::class);
    }

    // Relasi ke Document
    public function documents()
    {
        return $this->hasMany(ProjectDocument::class);
    }

    // Relasi ke Support
    public function supports()
    {
        return $this->hasMany(ProjectSupport::class);
    }

    // Relasi ke Technical Workflow
    public function surveys()
    {
        return $this->hasMany(Survey::class);
    }

    public function sizingProjects()
    {
        return $this->hasMany(SizingProject::class);
    }

    public function requestHargas()
    {
        return $this->hasMany(RequestHarga::class);
    }

    public function instalasis()
    {
        return $this->hasMany(Instalasi::class);
    }
}
