<?php

namespace App\Console\Commands;

use App\Models\LeadActivity;
use App\Models\Proposal;
use App\Notifications\ProposalStatusNotification;
use Illuminate\Console\Command;

class ExpireProposals extends Command
{
    protected $signature = 'proposals:expire';

    protected $description = 'Tandai proposal yang melewati valid_until sebagai expired.';

    public function handle(): int
    {
        $expired = Proposal::whereIn('status', ['ready', 'sent', 'viewed'])
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', today())
            ->get();

        foreach ($expired as $proposal) {
            $proposal->update(['status' => 'expired']);

            LeadActivity::create([
                'lead_id' => $proposal->lead_id,
                'user_id' => null,
                'action' => 'proposal_expired',
                'changes' => ['number' => $proposal->proposal_number],
            ]);

            // pending_technical sengaja tidak ikut expire (masih digarap teknis).
            if ($assignee = $proposal->lead->assignee) {
                $assignee->notify(new ProposalStatusNotification($proposal, 'expired'));
            }
        }

        $this->info("Expired {$expired->count()} proposal.");

        return self::SUCCESS;
    }
}
