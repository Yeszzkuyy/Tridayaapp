<?php

namespace App\Notifications;

use App\Models\PriceRequest;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke Sales saat tim engineer mengunggah file balasan (harga siap). */
class PriceRequestCompletedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public PriceRequest $request) {}

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
            'type' => 'price_request_completed',
            'price_request_id' => $this->request->id,
            'customer_id' => $this->request->customer_id,
            'title' => $this->request->customer?->name ?? ('#'.$this->request->customer_id),
            'url' => route('sales.price-requests.show', $this->request->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Balasan Request Harga Sudah Tersedia')
            ->line('Harga untuk '.($this->request->customer?->name ?? '-').' sudah diunggah tim Engineer.')
            ->action('Lihat Balasan', url(route('sales.price-requests.show', $this->request->id)))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Price Reply Available'),
            'body' => $this->request->customer?->name ?? '',
            'url' => route('sales.price-requests.show', $this->request->id),
        ];
    }
}
