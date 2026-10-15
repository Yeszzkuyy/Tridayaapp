<?php

namespace App\Console\Commands;

use App\Models\FollowUp;
use App\Notifications\FollowUpOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendFollowUpReminders extends Command
{
    protected $signature = 'followups:remind';

    protected $description = 'Kirim pengingat follow-up sales yang tanggalnya sudah lewat.';

    public function handle(): int
    {
        $today = today();

        $candidates = FollowUp::with(['customer', 'creator', 'lead.assignee'])
            ->whereNull('reminder_sent_at')
            ->whereNull('completed_at')
            ->whereNotNull('follow_up_date')
            ->whereDate('follow_up_date', '<', $today)
            ->get();

        $sent = 0;

        foreach ($candidates as $followUp) {
            $target = $followUp->lead?->assignee ?? $followUp->creator;

            if (! $target) {
                continue;
            }

            try {
                $target->notify(new FollowUpOverdueNotification($followUp));
                $followUp->forceFill(['reminder_sent_at' => now()])->saveQuietly();
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Reminder follow-up gagal: '.$e->getMessage());
            }
        }

        $this->info("Reminder terkirim: {$sent}.");

        return self::SUCCESS;
    }
}
