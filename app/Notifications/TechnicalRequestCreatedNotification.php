<?php

namespace App\Notifications;

use App\Models\TechnicalRequest;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke semua Lead Teknisi saat Sales membuat Technical Request. */
class TechnicalRequestCreatedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public TechnicalRequest $request) {}

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
            'type' => 'technical_request_created',
            'technical_request_id' => $this->request->id,
            'lead_id' => $this->request->lead_id,
            'title' => $this->request->title,
            'priority' => $this->request->priority,
            'url' => route('sales.technical-requests.show', $this->request->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Technical Request Baru Perlu Direview')
            ->line('Lead: ' . ($this->request->lead->customer?->name ?? '#'.$this->request->lead_id))
            ->line('Request: ' . $this->request->title . ' (' . TechnicalRequest::typeLabel($this->request->request_type) . ')')
            ->action('Review Request', url(route('sales.technical-requests.show', $this->request->id)))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => 'Technical Request baru',
            'body' => $this->request->title,
            'url' => route('sales.technical-requests.show', $this->request->id),
        ];
    }
}
