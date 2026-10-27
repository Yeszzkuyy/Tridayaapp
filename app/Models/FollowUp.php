<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FollowUp extends Model
{
    use SoftDeletes;

    /**
     * Pilihan cara menghubungi yang ditawarkan di form (tanpa 'follow_up'
     * dan 'meeting' yang tumpang tindih dengan nama menu/fitur).
     * Nilai lama tetap valid dibaca, tapi tidak ditawarkan lagi.
     */
    public const CONTACT_TYPES = ['call', 'whatsapp', 'email', 'note'];

    public const LEGACY_TYPES = ['follow_up', 'meeting'];

    /** Opsi Log Follow Up di detail lead (meeting dipisah ke menu Meeting). */
    public const LOG_TYPES = ['whatsapp', 'call', 'email', 'proposal'];

    /** Lokasi meeting untuk tracker Meeting (dipisah dari Follow Up). */
    public const MEETING_MODES = ['online', 'offline'];

    /** Meeting outcome options (diisi saat sales complete meeting). */
    public const MEETING_OUTCOMES = [
        'prospect',
        'need_quotation',
        'follow_up',
        'negotiation',
        'closed_won',
        'closed_lost',
    ];

    protected $fillable = [
        'customer_id',
        'lead_id',
        'description',
        'type',
        'follow_up_date',
        'activity_time',
        'location',
        'meeting_mode',
        'next_follow_up_date',
        'reminder_sent_at',
        'completed_at',
        'created_by',
        // Meeting Report / MOM fields
        'meeting_photos',
        'meeting_notes',
        'meeting_outcome',
        'meeting_next_action_date',
    ];

    protected $casts = [
        'follow_up_date' => 'date',
        'next_follow_up_date' => 'date',
        'reminder_sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'meeting_photos' => 'array',
        'meeting_next_action_date' => 'date',
    ];

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            'call' => __('Telepon'),
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'meeting' => __('Visit/Meeting'),
            'proposal' => __('Send Offer'),
            'note' => __('Notes'),
            default => __('Follow Up'),
        };
    }

    /**
     * Human-readable label for meeting outcome.
     */
    public static function outcomeLabel(?string $outcome): string
    {
        return match ($outcome) {
            'prospect' => __('Prospect'),
            'need_quotation' => __('Need Quotation'),
            'follow_up' => __('Needs Follow-up'),
            'negotiation' => __('Negotiation'),
            'closed_won' => __('Closed Won'),
            'closed_lost' => __('Closed Lost'),
            default => '-',
        };
    }

    /**
     * CSS badge classes for outcome display.
     */
    public static function outcomeBadgeClass(?string $outcome): string
    {
        return match ($outcome) {
            'prospect' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200',
            'need_quotation' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200',
            'follow_up' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/50 dark:text-orange-200',
            'negotiation' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-200',
            'closed_won' => 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-200',
            'closed_lost' => 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-200',
            default => 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300',
        };
    }

    /**
     * Whether this follow-up has a completed meeting report.
     */
    public function hasMeetingReport(): bool
    {
        return $this->completed_at !== null
            && $this->meeting_notes !== null;
    }

    public static function meetingModeLabel(?string $mode): string
    {
        return match ($mode) {
            'online' => __('Online'),
            'offline' => __('Offline'),
            // Nilai lama (sebelum disederhanakan) tetap bisa dibaca.
            'office' => __('Office'),
            'field' => __('Field'),
            default => '-',
        };
    }

    /** Scope: hanya entri Meeting (menu Meeting terpisah dari Follow Up). */
    public function scopeMeetings($query)
    {
        return $query->where('type', 'meeting');
    }

    /** Scope: Follow Up non-meeting (termasuk data lama bertipe null). */
    public function scopeNonMeetings($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('type')->orWhere('type', '!=', 'meeting');
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
