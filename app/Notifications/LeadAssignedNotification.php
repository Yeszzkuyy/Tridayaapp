<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke sales saat lead di-assign kepadanya. */
class LeadAssignedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public Lead $lead) {}

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

    /**
     * Deep link ke My Leads milik sales (terfilter nama customer),
     * dipakai bell notifikasi + web push. Email tetap ke detail lead.
     */
    public static function myLeadsUrl(?string $customer): ?string
    {
        $customer = trim((string) $customer);

        return $customer === ''
            ? route('sales.my-leads')
            : route('sales.my-leads', ['search' => $customer]);
    }

    public function toDatabase(object $notifiable): array
    {
        $customer = $this->lead->customer?->name;

        return [
            'type' => 'assigned',
            'lead_id' => $this->lead->id,
            'customer' => $customer ?? __('New lead'),
            'url' => self::myLeadsUrl($customer),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Lead Di-assign ke Anda')
            ->line('Lead berikut di-assign ke Anda dan membutuhkan penanganan:')
            ->line('Customer: ' . ($this->lead->customer?->name ?? 'Lead baru'))
            ->action('Lihat Lead', url(route('leads.show', $this->lead->id)))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => 'Lead di-assign ke Anda',
            'body' => 'Customer: ' . ($this->lead->customer?->name ?? __('New lead')),
            'url' => self::myLeadsUrl($this->lead->customer?->name),
        ];
    }
}
