<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\User;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke Sales pemilik proyek dan Management saat invoice lunas.
 * Target sales dihitung dari uang masuk, jadi persentase capaian ikut
 * disebut supaya sales langsung tahu dampaknya.
 */
class InvoicePaidSalesNotification extends Notification implements ShouldQueue
{
    use Queueable, SendsWebPush;

    public function __construct(
        public Invoice $invoice,
        public ?float $targetPct = null,
    ) {}

    private function isSupervisor(object $notifiable): bool
    {
        return $notifiable instanceof User && $notifiable->can('manage-sales-leads');
    }

    private function relativeUrl(object $notifiable): string
    {
        return $this->isSupervisor($notifiable)
            ? route('manage-sales.kpi', [], false)
            : route('sales.dashboard', [], false);
    }

    private function body(object $notifiable): string
    {
        $customer = $this->invoice->customer?->name ?? '-';
        $amount = number_format((float) $this->invoice->amount, 0, ',', '.');

        if ($this->isSupervisor($notifiable)) {
            return __('Invoice :number for :customer is fully paid (Rp :amount).', [
                'number' => $this->invoice->invoice_number,
                'customer' => $customer,
                'amount' => $amount,
            ]);
        }

        return __('Invoice :number for :customer is fully paid (Rp :amount). Your target achievement is now :pct%.', [
            'number' => $this->invoice->invoice_number,
            'customer' => $customer,
            'amount' => $amount,
            'pct' => number_format((float) ($this->targetPct ?? 0), 1, ',', '.'),
        ]);
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
            'type' => 'invoice_paid',
            'invoice_id' => $this->invoice->id,
            'invoice_number' => $this->invoice->invoice_number,
            'customer_id' => $this->invoice->customer_id,
            'customer' => $this->invoice->customer?->name ?? '-',
            'amount' => (float) $this->invoice->amount,
            'target_pct' => $this->targetPct,
            'title' => __('Invoice fully paid'),
            'preview' => $this->body($notifiable),
            'url' => $this->relativeUrl($notifiable),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Invoice fully paid'))
            ->line($this->body($notifiable))
            ->action(__('Open Dashboard'), url($this->relativeUrl($notifiable)))
            ->line(__('Thank you.'));
    }

    protected function pushUrl(object $notifiable): string
    {
        return $this->relativeUrl($notifiable);
    }

    protected function pushContent(): array
    {
        return [
            'title' => __('Invoice fully paid'),
            'body' => $this->invoice->customer?->name ?? $this->invoice->invoice_number,
            'url' => route('sales.dashboard'),
        ];
    }
}
