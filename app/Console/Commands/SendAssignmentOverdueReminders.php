<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Notifications\LeadAssignmentOverdueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendAssignmentOverdueReminders extends Command
{
    protected $signature = 'leads:overdue';

    protected $description = 'Kirim pengingat untuk lead yang di-assign lebih dari 24 jam dan belum ditindaklanjuti.';

    public function handle(): int
    {
        $sent = 0;

        Lead::assignmentOverdue()
            ->whereNull('assignment_overdue_notified_at')
            ->with(['assignee', 'customer'])
            ->chunkById(100, function ($leads) use (&$sent) {
                foreach ($leads as $lead) {
                    $target = $lead->assignee;

                    if (! $target) {
                        continue;
                    }

                    try {
                        $target->notify(new LeadAssignmentOverdueNotification($lead));
                        $lead->forceFill(['assignment_overdue_notified_at' => now()])->saveQuietly();
                        $sent++;
                    } catch (\Throwable $e) {
                        Log::warning('Pengingat overdue lead gagal: '.$e->getMessage());
                    }
                }
            });

        $this->info("Pengingat overdue terkirim: {$sent}.");

        return self::SUCCESS;
    }
}
