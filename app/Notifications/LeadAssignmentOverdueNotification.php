<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim sekali saat lead lewat 24 jam belum ditindaklanjuti. */
class LeadAssignmentOverdueNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public Lead $lead) {}

    public static function indexUrl(): string
    {
        return route('sales.my-leads', ['overdue' => 1]);
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
            'type' => 'lead_overdue',
            'lead_id' => $this->lead->id,
            'customer' => $this->lead->customer?->name ?? __('New lead'),
            'url' => self::indexUrl(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Lead has not been followed up'))
            ->line(__('This lead was assigned more than 24 hours ago and has not been followed up:'))
            ->line(__('Customer').': '.($this->lead->customer?->name ?? __('New lead')))
            ->action(__('Open My Leads'), url(self::indexUrl()))
            ->line(__('Please contact the customer or move the lead stage.'));
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Lead has not been followed up'),
            'body' => __('Customer').': '.($this->lead->customer?->name ?? __('New lead')),
            'url' => self::indexUrl(),
        ];
    }
}
