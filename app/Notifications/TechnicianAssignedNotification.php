<?php

namespace App\Notifications;

use App\Models\TechnicalRequest;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke teknisi yang ditunjuk sebagai PIC pada request tim engineer. */
class TechnicianAssignedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public TechnicalRequest $request) {}

    private function title(): string
    {
        return __('You are assigned to :title', ['title' => $this->request->title]);
    }

    private function scheduleLine(): string
    {
        $date = $this->request->target_date;

        return $date
            ? __('Schedule').': '.$date->format('d M Y')
            : __('Schedule').': '.__('Not set yet');
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
            'type' => 'technician_assigned',
            'technical_request_id' => $this->request->id,
            'title' => $this->title(),
            'preview' => $this->request->lead?->customer?->name ?? '',
            'url' => route('teknisi.technical-requests.show', $this->request->id, false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line($this->scheduleLine())
            ->action(__('Open Request'), url(route('teknisi.technical-requests.show', $this->request->id)))
            ->line(__('Thank you.'));
    }

    protected function pushUrl(object $notifiable): string
    {
        return route('teknisi.technical-requests.show', $this->request->id);
    }

    protected function pushContent(): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->scheduleLine(),
            'url' => route('teknisi.technical-requests.show', $this->request->id),
        ];
    }
}
