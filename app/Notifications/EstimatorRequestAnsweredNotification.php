<?php

namespace App\Notifications;

use App\Models\EstimatorRequest;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EstimatorRequestAnsweredNotification extends Notification
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
            'type' => 'estimator_request_answered',
            'estimator_request_id' => $this->request->id,
            'customer_id' => $this->request->customer_id,
            'title' => $this->request->customer?->name ?? ('#'.$this->request->customer_id),
            'url' => $this->relativeUrl($notifiable),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Product price reply is available'))
            ->line(__('Customer').': '.($this->request->customer?->name ?? '-'))
            ->line(__('Status').': '.__('Price Received'))
            ->action(__('View details'), url($this->showUrl($notifiable)))
            ->line(__('Thank you.'));
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Product price reply is available'),
            'body' => $this->request->customer?->name ?? '',
            'url' => $this->showUrl($notifiable),
        ];
    }
}
