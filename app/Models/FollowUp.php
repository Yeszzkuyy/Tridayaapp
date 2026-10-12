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

    /** Opsi Log Follow Up di detail lead (urutan tampil di form). */
    public const LOG_TYPES = ['whatsapp', 'call', 'email', 'meeting', 'proposal'];

    protected $fillable = [
        'customer_id',
        'lead_id',
        'meeting_id',
        'description',
        'type',
        'follow_up_date',
        'activity_time',
        'location',
        'next_follow_up_date',
        'reminder_sent_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'follow_up_date' => 'date',
        'next_follow_up_date' => 'date',
        'reminder_sent_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public static function typeLabel(?string $type): string
    {
        return match ($type) {
            'call' => __('Telepon'),
            'whatsapp' => 'WhatsApp',
            'email' => 'Email',
            'meeting' => __('Kunjungan/Meeting'),
            'proposal' => __('Kirim Penawaran'),
            'note' => __('Catatan'),
            default => __('Follow Up'),
        };
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function meeting()
    {
        return $this->belongsTo(Meeting::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
