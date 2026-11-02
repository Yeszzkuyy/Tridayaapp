<?php

namespace App\Notifications;

use App\Models\Proposal;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke Admin/Purchasing saat quotation masuk alur handoff:
 * PO customer sudah diunggah, atau project sudah dibuat dari quotation.
 */
class PurchasingHandoffNotification extends Notification
{
    use Queueable, SendsWebPush;

    public const STAGE_PO_RECEIVED = 'po_received';

    public const STAGE_PROJECT_CREATED = 'project_created';

    public function __construct(public Proposal $proposal, public string $stage) {}

    private function isProjectCreated(): bool
    {
        return $this->stage === self::STAGE_PROJECT_CREATED;
    }

    private function title(): string
    {
        return $this->isProjectCreated()
            ? __('Project created from :number', ['number' => $this->proposal->proposal_number])
            : __('Customer PO received: :number', ['number' => $this->proposal->proposal_number]);
    }

    private function customerName(): string
    {
        return (string) ($this->proposal->lead?->customer?->name ?? $this->proposal->customer ?? '-');
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
            'type' => 'purchasing_handoff',
            'proposal_id' => $this->proposal->id,
            'project_id' => $this->proposal->project?->id,
            'stage' => $this->stage,
            'title' => $this->title(),
            'preview' => $this->customerName(),
            // Relatif: klik di aplikasi tetap di domain tempat user login.
            'url' => route('admin.purchasing.show', $this->proposal->id, false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->line(__('Customer').': '.$this->customerName())
            ->line(__('Quotation').': '.$this->proposal->proposal_number)
            ->action(__('Open Purchasing Queue'), url(route('admin.purchasing.show', $this->proposal->id)))
            ->line(__('Thank you.'));
    }

    protected function pushUrl(object $notifiable): string
    {
        return route('admin.purchasing.show', $this->proposal->id);
    }

    protected function pushContent(): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->customerName(),
            'url' => route('admin.purchasing.show', $this->proposal->id),
        ];
    }
}
