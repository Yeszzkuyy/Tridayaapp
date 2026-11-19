<?php

namespace App\Notifications;

use App\Models\Lead;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke sales lama saat lead-nya dipindahkan ke sales lain. */
class LeadReassignedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public Lead $lead, public ?string $newAssigneeName = null) {}

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
            'type' => 'reassigned',
            'lead_id' => $this->lead->id,
            'customer' => $this->lead->customer?->name ?? __('Lead'),
            'new_assignee' => $this->newAssigneeName,
            'url' => null,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $customer = $this->lead->customer?->name ?? __('Lead');

        return (new MailMessage)
            ->subject(__('Lead moved to another sales'))
            ->line(__('The following lead has been moved to :sales:', [
                'sales' => $this->newAssigneeName ?: __('another sales'),
            ]))
            ->line(__('Customer: :customer', ['customer' => $customer]))
            ->line(__('Your history and activity on this lead stay recorded.'));
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Lead moved to another sales'),
            'body' => $this->newAssigneeName
                ? $this->lead->customer?->name.' · '.$this->newAssigneeName
                : ($this->lead->customer?->name ?? __('Lead')),
            'url' => route('sales.my-leads'),
        ];
    }
}
