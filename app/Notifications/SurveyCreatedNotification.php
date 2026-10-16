<?php

namespace App\Notifications;

use App\Models\Survey;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Dikirim ke Sales (requester) saat Survey dibuat dari Technical Request-nya. */
class SurveyCreatedNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(public Survey $survey) {}

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
            'type' => 'survey_created',
            'survey_id' => $this->survey->id,
            'technical_request_id' => $this->survey->technical_request_id,
            'title' => $this->survey->title,
            'url' => route('teknisi.surveys.show', $this->survey->id),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Survey Dibuat dari Permintaan Anda')
            ->line('Survey "' . $this->survey->title . '" sudah dibuat dari permintaan teknis Anda.')
            ->line('Teknisi akan mengerjakan dan mengunggah laporannya.')
            ->action('Lihat Survey', url(route('teknisi.surveys.show', $this->survey->id)))
            ->line('Terima kasih.');
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Survey dibuat'),
            'body' => $this->survey->title,
            'url' => route('teknisi.surveys.show', $this->survey->id),
        ];
    }
}
