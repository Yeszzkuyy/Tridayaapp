<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TechnicianSchedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'project_id',
        'technician_user_id',
        'title',
        'description',
        'location',
        'start_at',
        'end_at',
        'status',
        'reminder_minutes',
        'reminder_sent_at',
        'google_event_id',
        'google_calendar_id',
        'google_sync_status',
        'google_sync_error',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'reminder_minutes' => 'integer',
        'reminder_sent_at' => 'datetime',
    ];

    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'on_progress' => 'On Progress',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public const SYNC = [
        'not_connected' => 'Not Connected',
        'syncing' => 'Syncing',
        'synced' => 'Synced',
        'error' => 'Sync Error',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    /**
     * Semua teknisi yang menangani jadwal ini (bisa lebih dari satu).
     */
    public function technicians()
    {
        return $this->belongsToMany(User::class, 'technician_schedule_user', 'technician_schedule_id', 'user_id');
    }

    /**
     * Daftar teknisi yang bertugas — pivot utama, fallback kolom lama
     * (jadwal lama yang belum tersentuh pivot tetap tampil).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function technicianList()
    {
        if ($this->technicians->isNotEmpty()) {
            return $this->technicians;
        }

        return $this->technician ? collect([$this->technician]) : collect();
    }

    public function scopeBetween($query, $start, $end)
    {
        return $query->where('start_at', '<', $end)->where('end_at', '>', $start);
    }
}
