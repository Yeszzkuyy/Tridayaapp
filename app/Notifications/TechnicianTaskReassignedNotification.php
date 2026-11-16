<?php

namespace App\Notifications;

use App\Models\TechnicalRequest;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke engineer yang dilepas saat task dipindahkan ke engineer lain. */
class TechnicianTaskReassignedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public TechnicalRequest $request, public ?string $newEngineerName = null) {}

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
            'type' => 'technician_task_reassigned',
            'technical_request_id' => $this->request->id,
            'title' => __('Task moved to another engineer'),
            'task' => $this->request->title,
            'preview' => $this->request->lead?->customer?->name ?? '',
            'new_engineer' => $this->newEngineerName,
            'url' => route('teknisi.tasks.index', [], false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Task moved to another engineer'))
            ->line(__('The following task is now handled by :engineer:', [
                'engineer' => $this->newEngineerName ?: __('another engineer'),
            ]))
            ->line($this->request->title)
            ->action(__('Open My Tasks'), url(route('teknisi.tasks.index')))
            ->line(__('Thank you.'));
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Task moved to another engineer'),
            'body' => $this->request->title,
            'url' => route('teknisi.tasks.index'),
        ];
    }
}
