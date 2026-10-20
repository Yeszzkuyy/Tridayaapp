<?php

namespace App\Notifications;

use App\Models\PriceRequest;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke Sales (peminta) saat Lead Teknisi mengambil/menolak request harga. */
class PriceRequestReviewedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public PriceRequest $request, public string $decision) {}

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
            'type' => 'price_request_reviewed',
            'price_request_id' => $this->request->id,
            'customer_id' => $this->request->customer_id,
            'title' => $this->request->customer?->name ?? ('#'.$this->request->customer_id),
            'decision' => $this->decision,
            'url' => route('sales.price-requests.show', $this->request->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Request Harga '.ucfirst($this->decision))
            ->line('Request harga untuk '.($this->request->customer?->name ?? '-').' telah: '.$this->decision.'.')
            ->action('Lihat Request', url(route('sales.price-requests.show', $this->request->id)))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Price Request').' '.$this->decision,
            'body' => $this->request->customer?->name ?? '',
            'url' => route('sales.price-requests.show', $this->request->id),
        ];
    }
}
