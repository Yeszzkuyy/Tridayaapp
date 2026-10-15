<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    public const PT_GROUPS = ['NTI', 'MGK', 'TPS', 'WANI'];

    public const LOST_REASONS = ['price', 'competitor', 'budget', 'requirement_changed', 'no_response', 'other'];

    public static function lostReasonLabel(?string $reason): string
    {
        return match ($reason) {
            'price' => __('Harga'),
            'competitor' => __('Kompetitor'),
            'budget' => __('Budget'),
            'requirement_changed' => __('Kebutuhan berubah'),
            'no_response' => __('Tidak ada respon'),
            'other' => __('Lainnya'),
            default => '-',
        };
    }

    /**
     * Label status untuk area sales: lead entry ('cool') tampil sebagai
     * 'New' agar tidak rancu. Nilai DB tetap 'cool'.
     */
    public static function salesStatusLabel(?string $status): string
    {
        return $status === 'cool' ? __('New') : ucfirst((string) $status);
    }

    /**
     * Kelas warna badge status (sama dengan hero detail lead):
     * cool/New biru, warm kuning, hot oranye, won hijau, lost merah.
     */
    public static function statusBadgeClasses(?string $status): string
    {
        return match ($status) {
            'cool' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            'warm' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
            'hot' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
            'won' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'lost' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        };
    }

    public const PT_COLORS = [
        'NTI' => 'bg-sky-500 text-white',
        'MGK' => 'bg-blue-900 text-white',
        'TPS' => 'bg-red-500 text-white',
        'WANI' => 'bg-orange-500 text-white',
    ];

    protected $fillable = [
        'customer_id',
        'partner_id',
        'whatsapp_account_id',
        'pt_group',
        'segment',
        'status',
        'source',
        'kebutuhan',
        'solusi',
        'progress_notes',
        'notes',
        'incoming_date',
        'assigned_to',
        'acknowledged_at',
        'lost_reason',
        'lost_note',
        'closed_at',
        'closing_note',
    ];

    protected $casts = [
        'incoming_date' => 'date',
        'assigned_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function customer()
    {
        // Lead tetap harus menampilkan customernya walau customer sudah soft-deleted
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function activities()
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function documents()
    {
        return $this->hasMany(LeadDocument::class);
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    /**
     * Project milik customer dari lead ini, tanpa memuat relasi customer
     * (null-safe: customer_id kosong langsung menghasilkan koleksi kosong).
     */
    public function projects()
    {
        return $this->hasMany(Project::class, 'customer_id', 'customer_id');
    }

    public function whatsappAccount()
    {
        return $this->belongsTo(WhatsappAccount::class, 'whatsapp_account_id');
    }

    public function followUps()
    {
        return $this->hasMany(FollowUp::class);
    }

    public function tasks()
    {
        return $this->hasMany(LeadTask::class);
    }

    public function salesSchedules()
    {
        return $this->hasMany(SalesSchedule::class);
    }

    public function technicalRequests()
    {
        return $this->hasMany(TechnicalRequest::class);
    }

    public function proposals()
    {
        return $this->hasMany(Proposal::class);
    }
}