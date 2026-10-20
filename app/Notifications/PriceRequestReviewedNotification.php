<?php

namespace App\Notifications;

use App\Models\PriceRequest;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke Sales (peminta) saat Lead Teknisi mengambil/menolak request harga. */
class PriceRequestReviewedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public PriceRequest $request, public string $decision) {}

    /** Tujuan klik notifikasi: teknisi → menu Teknisi, sales → menu Sales. */
    private function showUrl(object $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->can('manage-technician')
            ? route('teknisi.price-requests.show', $this->request->id)
            : route('sales.price-requests.show', $this->request->id);
    }

    /** URL in-app: relatif supaya tetap di domain tempat user login. */
    private function relativeUrl(object $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->can('manage-technician')
            ? route('teknisi.price-requests.show', $this->request->id, false)
            : route('sales.price-requests.show', $this->request->id, false);
    }

    protected function pushUrl(object $notifiable): string
    {
        return $this->showUrl($notifiable);
    }

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
            'url' => $this->relativeUrl($notifiable),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Request Harga '.ucfirst($this->decision))
            ->line('Request harga untuk '.($this->request->customer?->name ?? '-').' telah: '.$this->decision.'.')
            ->action('Lihat Request', url($this->showUrl($notifiable)))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Price Request').' '.$this->decision,
            'body' => $this->request->customer?->name ?? '',
            'url' => $this->showUrl($notifiable),
        ];
    }
}
