<?php

namespace App\Notifications;

use App\Models\TechnicalRequest;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke engineer utama + support saat request dibatalkan. */
class TechnicianTaskCancelledNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public TechnicalRequest $request) {}

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
            'type' => 'technician_task_cancelled',
            'technical_request_id' => $this->request->id,
            'title' => __('Task cancelled'),
            'task' => $this->request->title,
            'preview' => $this->request->lead?->customer?->name ?? '',
            'url' => route('teknisi.technical-requests.show', $this->request->id, false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Task cancelled: :title', ['title' => $this->request->title]))
            ->line(__('This task has been cancelled by the requester. No further action is needed.'))
            ->line($this->request->title)
            ->action(__('Open Request'), url(route('teknisi.technical-requests.show', $this->request->id)))
            ->line(__('Thank you.'));
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Task cancelled: :title', ['title' => $this->request->title]),
            'body' => $this->request->lead?->customer?->name ?? '',
            'url' => route('teknisi.technical-requests.show', $this->request->id),
        ];
    }
}
