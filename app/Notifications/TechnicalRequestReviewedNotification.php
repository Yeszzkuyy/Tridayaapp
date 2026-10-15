<?php

namespace App\Notifications;

use App\Models\TechnicalRequest;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke Sales (requester) saat Lead Teknisi accept/reject/assign. */
class TechnicalRequestReviewedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public TechnicalRequest $request, public string $decision) {}

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
            'type' => 'technical_request_reviewed',
            'technical_request_id' => $this->request->id,
            'lead_id' => $this->request->lead_id,
            'title' => $this->request->title,
            'decision' => $this->decision,
            'url' => route('sales.technical-requests.show', $this->request->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Technical Request ' . ucfirst($this->decision))
            ->line('Request "' . $this->request->title . '" telah: ' . $this->decision . '.')
            ->action('Lihat Progress', url(route('sales.technical-requests.show', $this->request->id)))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => 'Technical Request ' . $this->decision,
            'body' => $this->request->title,
            'url' => route('sales.technical-requests.show', $this->request->id),
        ];
    }
}
