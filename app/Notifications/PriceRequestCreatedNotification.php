<?php

namespace App\Notifications;

use App\Models\PriceRequest;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke pricing engineer khusus, atau lead technician sebagai fallback. */
class PriceRequestCreatedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public PriceRequest $request) {}

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
            'type' => 'price_request_created',
            'price_request_id' => $this->request->id,
            'customer_id' => $this->request->customer_id,
            'title' => $this->request->customer?->name ?? ('#'.$this->request->customer_id),
            'url' => $this->relativeUrl($notifiable),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Request Harga Baru dari Sales')
            ->line('Customer: '.($this->request->customer?->name ?? '#'.$this->request->customer_id))
            ->line('Dari: '.($this->request->requester?->name ?? '-'))
            ->action('Lihat Request', url($this->showUrl($notifiable)))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('New Price Request'),
            'body' => $this->request->customer?->name ?? '',
            'url' => $this->showUrl($notifiable),
        ];
    }
}
