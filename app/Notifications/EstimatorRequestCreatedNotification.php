<?php

namespace App\Notifications;

use App\Models\EstimatorRequest;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EstimatorRequestCreatedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public EstimatorRequest $request) {}

    private function showUrl(object $notifiable): string
    {
        return route('estimator.requests.show', $this->request->id);
    }

    private function relativeUrl(object $notifiable): string
    {
        return route('estimator.requests.show', $this->request->id, false);
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

        return $this->withWebPush($notifiable, $this->withMail($notifiable, $channels));
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'estimator_request_created',
            'estimator_request_id' => $this->request->id,
            'customer_id' => $this->request->customer_id,
            'title' => $this->request->customer?->name ?? ('#'.$this->request->customer_id),
            'url' => $this->relativeUrl($notifiable),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New product price request'))
            ->line(__('Customer').': '.($this->request->customer?->name ?? '-'))
            ->line(__('Requested by').': '.($this->request->requester?->name ?? '-'))
            ->action(__('View details'), url($this->showUrl($notifiable)))
            ->line(__('Thank you.'));
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('New product price request'),
            'body' => $this->request->customer?->name ?? '',
            'url' => $this->showUrl($notifiable),
        ];
    }
}
