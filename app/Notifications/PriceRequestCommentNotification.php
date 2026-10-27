<?php

namespace App\Notifications;

use App\Models\PriceRequest;
use App\Models\PriceRequestComment;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Notifies the other Price Request participant when a chat message is sent. */
class PriceRequestCommentNotification extends Notification
{
    use Queueable, SendsWebPush;

    public function __construct(
        public PriceRequest $request,
        public PriceRequestComment $comment,
    ) {}

    private function showUrl(object $notifiable): string
    {
        return $notifiable instanceof User && $notifiable->can('manage-technician')
            ? route('teknisi.price-requests.show', $this->request->id)
            : route('sales.price-requests.show', $this->request->id);
    }

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
            'type' => 'price_request_comment',
            'price_request_id' => $this->request->id,
            'customer_id' => $this->request->customer_id,
            'title' => $this->request->customer?->name ?? ('#'.$this->request->customer_id),
            'sender_name' => $this->comment->user?->name ?? __('System'),
            'preview' => Str::limit($this->comment->body, 140),
            'url' => $this->relativeUrl($notifiable),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New message in Price Request'))
            ->line(($this->comment->user?->name ?? __('System')).': '.$this->comment->body)
            ->action(__('Open Price Request'), url($this->showUrl($notifiable)));
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('New message in Price Request'),
            'body' => $this->request->customer?->name ?? '',
            'url' => route('sales.price-requests.show', $this->request->id),
        ];
    }
}
