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
            'created' => __('Added lead'),
            'updated' => __('Updated lead'),
            'deleted' => __('Deleted lead'),
            'attachment_deleted' => __('Deleted lead attachment'),
            'converted' => __('Converted lead to Project'),
            'status_changed' => __('Moved lead position in pipeline'),
            'assigned' => __('Assigned lead to sales'),
            'reassigned' => __('Reassigned lead to another sales'),
            'meeting_created' => __('Logged a meeting'),
            'proposal_created' => __('Created proposal'),
            'proposal_sent' => __('Sent proposal'),
            'proposal_revised' => __('Revised proposal'),
            'proposal_accepted' => __('Proposal accepted by customer (WON)'),
            'proposal_rejected' => __('Proposal rejected by customer'),
            'proposal_expired' => __('Proposal validity expired'),
            'proposal_pending_technical' => __('Submitted proposal to technical team'),
            'proposal_ready' => __('Technical proposal done, ready to send'),
            'proposal_converted' => __('Converted proposal to Project'),
            'followup_created' => __('Logged a follow up'),
            'followup_completed' => __('Completed a follow up'),
            'followup_snoozed' => __('Postponed a follow up'),
            'task_created' => __('Created an inside sales task'),
            'task_status_changed' => __('Updated inside sales task status'),
            default => $this->action,
        };
    }
}
