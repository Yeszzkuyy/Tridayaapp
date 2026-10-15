<?php

namespace App\Notifications;

use App\Models\Proposal;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke PIC lead saat proposal dikirim/diubah status penting oleh orang lain. */
class ProposalStatusNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public Proposal $proposal, public string $event) {}

    public function via(object $notifiable): array
    {
        if ($this->muted($notifiable)) {
            return [];
        }

        $channels = [];

        if ($notifiable->preference('notify_system', true)) {
            $channels[] = 'database';
        }

        $channels = $this->withMail($notifiable, $channels);

        return $this->withWebPush($notifiable, $channels);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'proposal_status',
            'proposal_id' => $this->proposal->id,
            'lead_id' => $this->proposal->lead_id,
            'proposal_number' => $this->proposal->proposal_number,
            'event' => $this->event,
            'url' => route('sales.proposals.show', $this->proposal->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Proposal ' . $this->proposal->proposal_number . ': ' . $this->event)
            ->line('Proposal ' . $this->proposal->proposal_number . ' mengalami perubahan: ' . $this->event . '.')
            ->action('Lihat Proposal', url(route('sales.proposals.show', $this->proposal->id)))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => 'Proposal ' . $this->event,
            'body' => $this->proposal->proposal_number,
            'url' => route('sales.proposals.show', $this->proposal->id),
        ];
    }
}
