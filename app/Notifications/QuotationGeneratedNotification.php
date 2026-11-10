<?php

namespace App\Notifications;

use App\Models\Proposal;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi ke Sales saat Inside Sales selesai membuat Quotation + PDF.
 * Sengaja tidak memakai queue agar tidak bergantung pada izin tabel jobs.
 */
class QuotationGeneratedNotification extends Notification
{
    public function __construct(public Proposal $proposal) {}

    public function via(object $notifiable): array
    {
        return $notifiable->preference('notify_system', true) ? ['database'] : [];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'proposal_status',
            'proposal_id' => $this->proposal->id,
            'lead_id' => $this->proposal->lead_id,
            'proposal_number' => $this->proposal->proposal_number,
            'customer' => $this->proposal->customer,
            'event' => 'created',
            'url' => route('sales.proposals.show', $this->proposal->id),
        ];
    }
}
