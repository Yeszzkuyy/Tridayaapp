<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    public const PT_GROUPS = ['NTI', 'MGK', 'TPS', 'WANI'];

    public const LOST_REASONS = ['price', 'competitor', 'budget', 'requirement_changed', 'no_response', 'other'];

    public const NEED_TYPES = ['cctv', 'pabx', 'video_conference', 'network', 'other'];

    public const PROCUREMENT_STATUSES = ['new', 'replacement'];

    /** Warna penanda lead (null = default). */
    public const COLOR_TAGS = ['red', 'yellow', 'green'];

    public static function needTypeLabel(?string $needType): string
    {
        return match ($needType) {
            'cctv' => __('CCTV'),
            'pabx' => __('PABX'),
            'video_conference' => __('Video Conference'),
            'network' => __('Network'),
            'other' => __('Others'),
            default => '-',
        };
    }

    public static function procurementStatusLabel(?string $status): string
    {
        return match ($status) {
            'new' => __('New Installation'),
            'replacement' => __('Replacement'),
            default => '-',
        };
    }

    public static function lostReasonLabel(?string $reason): string
    {
        return match ($reason) {
            'price' => __('Price'),
            'competitor' => __('Competitor'),
            'budget' => __('Budget'),
            'requirement_changed' => __('Requirements changed'),
            'no_response' => __('No response'),
            'other' => __('Others'),
            default => '-',
        };
    }

    /**
     * Label status untuk area sales: lead entry ('cool') tampil sebagai
     * 'New' agar tidak rancu. Nilai DB tetap 'cool'.
     */
    public static function salesStatusLabel(?string $status): string
    {
        return match ($status) {
            'cool' => __('New'),
            'warm' => __('Warm'),
            'hot' => __('Hot'),
            default => ucfirst((string) $status),
        };
    }

    /**
     * Ringkas kolom `kebutuhan` untuk label dropdown: rata jadi satu baris,
     * dipotong di batas $limit karakter. Kosong → string kosong.
     */
    public static function kebutuhanLabel(?string $kebutuhan, int $limit = 50): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $kebutuhan));
        if ($text === '') {
            return '';
        }

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit).'…' : $text;
    }

    /**
     * Label dropdown lead (form follow-up & modal jadwal):
     * "Lead #id — status — kebutuhan — tgl masuk" (bagian kosong dilewati).
     */
    public static function salesDropdownLabel(Lead $lead): string
    {
        $need = self::kebutuhanLabel($lead->kebutuhan);

        return "Lead #{$lead->id} — ".self::salesStatusLabel($lead->status)
            .($need !== '' ? ' — '.$need : '')
            .($lead->incoming_date ? ' — '.$lead->incoming_date->format('d M Y') : '');
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

    /** Warna stage khusus workspace Sales: Cool abu-abu, Warm kuning, Hot oranye. */
    public static function workspaceStageClasses(?string $status): string
    {
        return match ($status) {
            'cool' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
            'warm' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
            'hot' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
            'won' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
            'lost' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        };
    }

    /** Warna titik/penanda lead. */
    public static function colorDotClasses(?string $color): string
    {
        return match ($color) {
            'red' => 'bg-red-500',
            'yellow' => 'bg-amber-400',
            'green' => 'bg-emerald-500',
            default => 'bg-slate-300 dark:bg-slate-600',
        };
    }

    public static function colorLabel(?string $color): string
    {
        return match ($color) {
            'red' => __('Red'),
            'yellow' => __('Yellow'),
            'green' => __('Green'),
            default => __('Default'),
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
        'color_tag',
        'source',
        'kebutuhan',
        'project_name',
        'need_type',
        'procurement_status',
        'solusi',
        'progress_notes',
        'notes',
        'location',
        'incoming_date',
        'assigned_to',
        'acknowledged_at',
        'lost_reason',
        'lost_note',
        'closed_at',
        'closing_note',
        'assignment_overdue_notified_at',
    ];

    protected $casts = [
        'incoming_date' => 'date',
        'assigned_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'assignment_overdue_notified_at' => 'datetime',
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

    /** Catatan terakhir (untuk kolom Latest Note di My Leads). */
    public function latestFollowUp()
    {
        return $this->hasOne(FollowUp::class)->latestOfMany();
    }

    /** Catatan terakhir yang benar-benar berisi (baris antrean boleh kosong). */
    public function latestNoteFollowUp()
    {
        return $this->hasOne(FollowUp::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where('description', '!=', '')
                ->where(fn ($t) => $t->whereNull('type')->orWhere('type', '!=', 'meeting'))
        );
    }

    /** Aktivitas terakhir menurut tanggal aktivitas (kolom Last Activity). */
    public function latestActivityFollowUp()
    {
        return $this->hasOne(FollowUp::class)->ofMany([
            'follow_up_date' => 'max',
            'id' => 'max',
        ]);
    }

    /**
     * Follow-up yang masih menjadi antrean: belum selesai dan bukan meeting.
     * Satu lead + satu owner hanya punya satu antrean aktif.
     */
    public function activeFollowUp()
    {
        return $this->hasOne(FollowUp::class)->ofMany(
            ['follow_up_date' => 'min', 'id' => 'min'],
            function ($query) {
                $query->whereNull('completed_at')
                    ->where(function ($q) {
                        $q->whereNull('status')->orWhere('status', '!=', 'completed');
                    })
                    ->where(function ($q) {
                        $q->whereNull('type')->orWhere('type', '!=', 'meeting');
                    });
            }
        );
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

    /**
     * Overdue baru: lead di-assign lebih dari 24 jam, belum diakui/dipindah
     * tahap, dan belum ada follow-up sejak assignment.
     */
    public function isAssignmentOverdue(): bool
    {
        if (! $this->assigned_to || ! $this->assigned_at) {
            return false;
        }

        if ($this->acknowledged_at !== null || in_array($this->status, ['won', 'lost'], true)) {
            return false;
        }

        if ($this->assigned_at->gt(now()->subHours(24))) {
            return false;
        }

        if (array_key_exists('has_follow_up_since_assignment', $this->attributes)) {
            return ! (bool) $this->attributes['has_follow_up_since_assignment'];
        }

        return ! $this->followUps()
            ->where('created_at', '>=', $this->assigned_at)
            ->where(function ($q) {
                $q->where(fn ($d) => $d->whereNotNull('description')->where('description', '!=', ''))
                    ->orWhereNotNull('completed_at')
                    ->orWhereNotNull('outcome');
            })
            ->exists();
    }

    /** Scope untuk filter, KPI, dan notifikasi overdue assignment. */
    public function scopeAssignmentOverdue($query)
    {
        return $query->whereNotNull('assigned_to')
            ->whereNotNull('assigned_at')
            ->where('assigned_at', '<=', now()->subHours(24))
            ->whereNull('acknowledged_at')
            ->whereNotIn('status', ['won', 'lost'])
            ->whereDoesntHave('followUps', function ($q) {
                $q->whereColumn('follow_ups.created_at', '>=', 'leads.assigned_at')
                    ->where(function ($x) {
                        $x->where(fn ($d) => $d->whereNotNull('description')->where('description', '!=', ''))
                            ->orWhereNotNull('completed_at')
                            ->orWhereNotNull('outcome');
                    });
            });
    }

    /** Apakah ada follow-up nyata (bukan sekadar jadwal kosong) setelah assign. */
    public function followUpSinceAssignmentExists(): bool
    {
        return $this->followUps()
            ->where('created_at', '>=', $this->assigned_at)
            ->where(function ($q) {
                $q->where(fn ($d) => $d->whereNotNull('description')->where('description', '!=', ''))
                    ->orWhereNotNull('completed_at')
                    ->orWhereNotNull('outcome');
            })
            ->exists();
    }

    /**
     * Satu lead = satu quotation (wawancara Adi Santosa). true bila lead ini
     * sudah punya quotation aktif (belum soft-delete), apapun statusnya.
     */
    public function hasActiveProposal(): bool
    {
        return $this->proposals()->exists();
    }
}