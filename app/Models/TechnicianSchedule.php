<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TechnicianSchedule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'project_id',
        'customer_id',
        'technical_request_id',
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

    /**
     * Penanda internal untuk pengingat "Pagi Hari H".
     *
     * Kolom reminder_minutes bertipe integer, jadi nilai 0 dipakai sebagai
     * penanda (bukan menit). Null tetap berarti tanpa pengingat.
     */
    public const REMINDER_MORNING_OF_EVENT = 0;

    public const REMINDER_MORNING_TIME = '08:10:00';

    /** Opsi dropdown reminder: value form => label terjemahan. */
    public const REMINDER_OPTIONS = [
        '' => 'No reminder',
        '30' => '30 minutes before',
        '60' => '1 hour before',
        '120' => '2 hours before',
        '1440' => '1 day before',
        'morning_of_event' => 'Morning of event',
    ];

    /** Nilai form yang diterima (selain kosong). */
    public const REMINDER_VALUES = ['30', '60', '120', '1440', 'morning_of_event'];

    /**
     * Ubah nilai form menjadi nilai kolom reminder_minutes.
     */
    public static function normalizeReminderValue(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if ($value === 'morning_of_event' || $value === self::REMINDER_MORNING_OF_EVENT) {
            return self::REMINDER_MORNING_OF_EVENT;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $minutes = (int) $value;

        return $minutes > 0 ? $minutes : null;
    }

    /**
     * Nilai reminder untuk form/API ("morning_of_event" tidak bisa dibaca
     * langsung dari kolom integer).
     */
    public function getReminderValueAttribute(): string
    {
        if ($this->reminder_minutes === null) {
            return '';
        }

        if ($this->reminder_minutes === self::REMINDER_MORNING_OF_EVENT) {
            return 'morning_of_event';
        }

        return (string) $this->reminder_minutes;
    }

    /**
     * Waktu target pengingat dalam zona waktu aplikasi (WIB).
     */
    public function reminderAt(): ?Carbon
    {
        if ($this->reminder_minutes === null || $this->start_at === null) {
            return null;
        }

        if ($this->reminder_minutes === self::REMINDER_MORNING_OF_EVENT) {
            return $this->start_at->copy()->setTimeFromTimeString(self::REMINDER_MORNING_TIME);
        }

        return $this->start_at->copy()->subMinutes($this->reminder_minutes);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Customer jadwal non-project. Untuk jadwal yang terhubung project,
     * customer tetap bisa dibaca dari project (fallback).
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Customer efektif: kolom customer sendiri dulu, lalu customer project.
     */
    public function displayCustomer(): ?Customer
    {
        return $this->customer ?: $this->project?->customer;
    }

    /**
     * URL detail jadwal (kalender otomatis membuka panel jadwal ini).
     */
    public function calendarUrl(?string $from = null): string
    {
        return route('teknisi.jadwal', array_filter([
            'date' => $this->start_at?->setTimezone('Asia/Jakarta')->toDateString(),
            'open' => $this->id,
            'from' => $from,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    public function technicalRequest()
    {
        return $this->belongsTo(TechnicalRequest::class);
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
     * @return Collection<int, User>
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
