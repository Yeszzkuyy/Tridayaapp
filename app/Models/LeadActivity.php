<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadActivity extends Model
{
    protected $fillable = [
        'lead_id',
        'user_id',
        'action',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public const FIELD_LABELS = [
        'pt_group' => 'PT',
        'file' => 'Lampiran',
        'assigned_to' => 'Sales',
        'customer_id' => 'Customer',
        'segment' => 'Segment',
        'status' => 'Status',
        'source' => 'Sumber Lead',
        'kebutuhan' => 'Kebutuhan',
        'notes' => 'Catatan',
        'incoming_date' => 'Tanggal Masuk',
        'sender' => 'Pengirim WA',
    ];

    public function actionLabel(): string
    {
        return match ($this->action) {
            'created' => __('Menambahkan lead'),
            'updated' => __('Mengubah lead'),
            'deleted' => __('Menghapus lead'),
            'attachment_deleted' => __('Menghapus lampiran lead'),
            'converted' => __('Mengonversi lead menjadi Project'),
            'status_changed' => __('Memindahkan posisi lead di pipeline'),
            'assigned' => __('Meng-assign lead ke sales'),
            'reassigned' => __('Meng-assign ulang lead ke sales lain'),
            'meeting_created' => __('Mencatat meeting'),
            'proposal_created' => __('Membuat proposal'),
            'proposal_sent' => __('Mengirim proposal'),
            'proposal_revised' => __('Merevisi proposal'),
            'proposal_accepted' => __('Proposal disetujui customer (WON)'),
            'proposal_rejected' => __('Proposal ditolak customer'),
            'proposal_expired' => __('Masa berlaku proposal habis'),
            'proposal_pending_technical' => __('Menyerahkan proposal ke tim teknis'),
            'proposal_ready' => __('Proposal teknis selesai, siap dikirim'),
            'proposal_converted' => __('Mengonversi proposal menjadi Project'),
            'followup_created' => __('Mencatat follow up'),
            'followup_completed' => __('Menyelesaikan follow up'),
            'followup_snoozed' => __('Menunda follow up'),
            'task_created' => __('Membuat inside sales task'),
            'task_status_changed' => __('Mengubah status inside sales task'),
            default => $this->action,
        };
    }
}
